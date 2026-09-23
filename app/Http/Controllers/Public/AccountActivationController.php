<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AccountActivationChallenge;
use App\Models\AccountActivationLog;
use App\Models\DataUser;
use App\Models\User;
use App\Services\CredentialRevocationService;
use App\Services\MemberIdentityService;
use App\Services\PublicMemberEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountActivationController extends Controller
{
    public function __construct(
        private MemberIdentityService $identity,
        private PublicMemberEligibilityService $eligibility,
        private CredentialRevocationService $revocation
    ) {}

    public function check(Request $request)
    {
        if (! config('member_onboarding.activation_enabled')) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak tersedia.'], 503);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before:today'],
        ]);

        $startedAt = microtime(true);
        $name = $this->identity->normalizeText($validated['name']);
        $candidates = User::query()
            ->join('data_users', 'users.id', '=', 'data_users.id_users')
            ->whereRaw('LOWER(TRIM(users.name)) = ?', [$name])
            ->whereDate('data_users.tanggal_lahir', $validated['tanggal_lahir'])
            ->select('users.*')
            ->get()
            ->unique('id')
            ->filter(fn (User $user) => $this->eligibility->eligible($user))
            ->pluck('id')
            ->values()
            ->all();

        $token = Str::random(64);
        AccountActivationChallenge::create([
            'token_hash' => $this->identity->secretHash($token),
            'candidate_ids' => $candidates,
            'ip_hash' => $this->identity->ipHash($request->ip()),
            'user_agent_hash' => $this->identity->userAgentHash($request->userAgent()),
            'attempts_remaining' => (int) config('member_onboarding.activation.attempts', 5),
            'expires_at' => now()->addMinutes((int) config('member_onboarding.activation.ttl_minutes', 10)),
        ]);

        $this->log(null, 'challenge_created', $request, [
            'result' => count($candidates) === 1 ? 'single' : (count($candidates) > 1 ? 'ambiguous' : 'none'),
        ]);
        $this->delay($startedAt);

        return $this->noCache(response()->json([
            'success' => true,
            'message' => 'Lanjutkan verifikasi data anggota.',
            'data' => ['challenge_token' => $token],
        ]));
    }

    public function verify(Request $request)
    {
        if (! config('member_onboarding.activation_enabled')) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak tersedia.'], 503);
        }

        $validated = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tahun_masuk' => ['required', 'regex:/^\d{4}(?:-\d{2}-\d{2})?$/'],
        ]);

        if (! $this->revocation->sessionsAtomicallyRevocable()) {
            return response()->json([
                'success' => false,
                'code' => 'SESSION_REVOCATION_UNAVAILABLE',
                'message' => 'Layanan sementara tidak tersedia.',
            ], 503);
        }

        $result = DB::transaction(function () use ($request, $validated) {
            $challenge = AccountActivationChallenge::query()
                ->where('token_hash', $this->identity->secretHash($validated['challenge_token']))
                ->lockForUpdate()
                ->first();

            if (! $this->validChallenge($challenge, $request)) {
                return ['status' => 401];
            }

            $matching = User::query()
                ->whereIn('id', $challenge->candidate_ids ?: [])
                ->lockForUpdate()
                ->get()
                ->filter(function (User $user) use ($validated) {
                    if (! $this->eligibility->eligible($user)) {
                        return false;
                    }

                    $profile = DataUser::where('id_users', $user->id)->first();
                    $entryYear = substr($validated['tahun_masuk'], 0, 4);
                    $storedYear = $profile?->tahun_masuk ? date('Y', strtotime((string) $profile->tahun_masuk)) : '';

                    return hash_equals($this->identity->normalizeText($profile?->tempat_lahir), $this->identity->normalizeText($validated['tempat_lahir']))
                        && hash_equals($storedYear, $entryYear);
                })
                ->values();

            if ($matching->count() !== 1) {
                $challenge->decrement('attempts_remaining');
                $challenge->refresh();
                if ($challenge->attempts_remaining <= 0) {
                    $challenge->forceFill(['used_at' => now()])->save();
                }
                $this->log(null, 'verification_failed', $request, [
                    'attempts_remaining' => max(0, (int) $challenge->attempts_remaining),
                ]);

                return ['status' => $challenge->attempts_remaining <= 0 ? 429 : 200];
            }

            $user = $matching->first();
            $profile = DataUser::where('id_users', $user->id)->lockForUpdate()->firstOrFail();
            if (! $this->eligibility->eligible($user)) {
                return ['status' => 200];
            }

            $user->forceFill([
                'is_active' => '1',
                'password' => Hash::make('mzt12345'),
                'password_changed_at' => null,
                'email_verified_at' => null,
                'account_setup_required' => true,
                'remember_token' => Str::random(60),
            ])->save();
            $profile->forceFill(['is_active' => '1'])->save();
            $this->revocation->revoke($user);
            $challenge->forceFill(['used_at' => now()])->save();
            AccountActivationChallenge::query()
                ->whereJsonContains('candidate_ids', $user->id)
                ->whereNull('used_at')
                ->where('id', '!=', $challenge->id)
                ->update(['used_at' => now(), 'updated_at' => now()]);
            $this->log($user->id, 'identity_verified', $request);

            return ['status' => 200, 'id_anggota' => $user->id_anggota];
        });

        if (! isset($result['id_anggota'])) {
            return $this->noCache(response()->json([
                'success' => false,
                'message' => $result['status'] === 429
                    ? 'Terlalu banyak percobaan. Silakan mulai ulang.'
                    : 'Verifikasi tidak dapat diselesaikan.',
            ], $result['status']));
        }

        return $this->noCache(response()->json([
            'success' => true,
            'message' => 'Identitas berhasil diverifikasi.',
            'data' => ['id_anggota' => $result['id_anggota']],
        ]));
    }

    private function validChallenge(?AccountActivationChallenge $challenge, Request $request): bool
    {
        return $challenge !== null
            && $challenge->used_at === null
            && $challenge->expires_at->isFuture()
            && $challenge->attempts_remaining > 0
            && hash_equals($challenge->ip_hash, $this->identity->ipHash($request->ip()))
            && hash_equals($challenge->user_agent_hash, $this->identity->userAgentHash($request->userAgent()));
    }

    private function log(?int $userId, string $event, Request $request, array $metadata = []): void
    {
        AccountActivationLog::create([
            'user_id' => $userId,
            'event' => $event,
            'ip_hash' => $this->identity->ipHash($request->ip()),
            'user_agent_hash' => $this->identity->userAgentHash($request->userAgent()),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function delay(float $startedAt): void
    {
        $minimum = (int) config('member_onboarding.activation.response_delay_us', 0);
        $elapsed = (int) ((microtime(true) - $startedAt) * 1000000);
        if ($minimum > $elapsed) {
            usleep($minimum - $elapsed);
        }
    }

    private function noCache($response)
    {
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
