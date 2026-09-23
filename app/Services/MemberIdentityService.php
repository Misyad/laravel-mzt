<?php

namespace App\Services;

use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MemberIdentityService
{
    public function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public function normalizeText(?string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
    }

    public function normalizePhone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    public function ipHash(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }

    public function userAgentHash(?string $userAgent): string
    {
        return hash_hmac('sha256', mb_substr((string) $userAgent, 0, 500), (string) config('app.key'));
    }

    public function secretHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public function lockEmail(string $email): void
    {
        if (! Schema::hasTable('member_email_locks')) {
            return;
        }

        $emailHash = hash('sha256', $email);
        DB::table('member_email_locks')->insertOrIgnore([
            'email_hash' => $emailHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('member_email_locks')->where('email_hash', $emailHash)->lockForUpdate()->first();
    }

    public function emailAvailable(string $email, ?int $exceptUserId = null, ?int $exceptApplicationId = null): bool
    {
        $users = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
        if ($exceptUserId !== null) {
            $users->where('id', '!=', $exceptUserId);
        }

        if ($users->exists()) {
            return false;
        }

        if (! Schema::hasTable('member_applications') || ! Schema::hasColumn('member_applications', 'active_email')) {
            return true;
        }

        $applications = MemberApplication::query()->where('active_email', $email);
        if ($exceptApplicationId !== null) {
            $applications->where('id', '!=', $exceptApplicationId);
        }

        return ! $applications->exists();
    }

    public function updateUserEmail(User $user, ?string $email): void
    {
        $email = $this->normalizeEmail($email);
        $email = $email === '' ? null : $email;

        try {
            DB::transaction(function () use ($user, $email) {
                if ($email !== null) {
                    $this->lockEmail($email);
                }
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($email !== null && ! $this->emailAvailable($email, $locked->id)) {
                    throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
                }

                $locked->forceFill(['email' => $email])->save();
                $user->setRawAttributes($locked->getAttributes(), true);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['1062', '23000', '23505'], true)) {
                throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
            }

            throw $exception;
        }
    }
}
