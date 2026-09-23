<?php

namespace App\Services;

use App\Models\ApplicantSession;
use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CredentialRevocationService
{
    public function sessionsAvailable(): bool
    {
        if (config('session.driver') !== 'database') {
            return false;
        }

        try {
            $connection = $this->connectionName();
            $schema = $connection ? Schema::connection($connection) : Schema::getFacadeRoot();
            $table = config('session.table', 'sessions');

            return $schema->hasTable($table)
                && $schema->hasColumn($table, 'id')
                && $schema->hasColumn($table, 'user_id');
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function sessionsAtomicallyRevocable(): bool
    {
        try {
            $userConnection = (new User)->getConnectionName() ?: DB::getDefaultConnection();
            $sessionConnection = $this->connectionName() ?: DB::getDefaultConnection();

            return $userConnection === $sessionConnection && $this->sessionsAvailable();
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function revoke(User $user, ?string $exceptSessionId = null): void
    {
        $user->tokens()->delete();

        $query = DB::connection($this->connectionName())
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
        $this->revokeApplicantSessions($user);
    }

    public function revokeApplicantSessions(User $user): void
    {
        if (! Schema::hasTable('member_applications') || ! Schema::hasTable('applicant_sessions')) {
            return;
        }

        ApplicantSession::query()
            ->whereIn('member_application_id', MemberApplication::query()
                ->select('id')
                ->where('approved_user_id', $user->id))
            ->delete();
    }

    private function connectionName(): ?string
    {
        $connection = config('session.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
