<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetTokenMail;
use App\Models\AccountActivationLog;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\CredentialRevocationService;
use App\Services\MemberIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private MemberIdentityService $identity,
        private CredentialRevocationService $revocation
    ) {}

    public function forgot(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = $this->identity->normalizeEmail($validated['email']);
        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->whereNotNull('email_verified_at')
            ->first();

        if ($user) {
            $token = Str::random(64);
            DB::transaction(function () use ($user, $email, $token) {
                PasswordResetRequest::query()
                    ->where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->update(['used_at' => now()]);
                PasswordResetRequest::create([
                    'token_hash' => $this->identity->secretHash($token),
                    'user_id' => $user->id,
                    'email' => $email,
                    'expires_at' => now()->addMinutes((int) config('member_onboarding.password_reset.ttl_minutes', 30)),
                    'created_at' => now(),
                ]);
                $this->log($user->id, 'password_reset_requested');
            });
            try {
                Mail::to($email)->send(new PasswordResetTokenMail($token, $email));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar dan telah diverifikasi, petunjuk reset telah dikirim.',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ]);

        if ($validated['password'] === 'mzt12345') {
            throw ValidationException::withMessages(['password' => ['Password baru tidak boleh menggunakan password sementara.']]);
        }

        if (! $this->revocation->sessionsAtomicallyRevocable()) {
            return response()->json([
                'success' => false,
                'code' => 'SESSION_REVOCATION_UNAVAILABLE',
                'message' => 'Reset password sementara tidak tersedia.',
            ], 503);
        }

        $email = $this->identity->normalizeEmail($validated['email']);
        $completed = DB::transaction(function () use ($validated, $email) {
            $reset = PasswordResetRequest::query()
                ->where('token_hash', $this->identity->secretHash($validated['token']))
                ->lockForUpdate()
                ->first();

            if (! $reset || $reset->used_at !== null || $reset->expires_at->isPast() || ! hash_equals($reset->email, $email)) {
                return false;
            }

            $user = User::query()
                ->whereKey($reset->user_id)
                ->whereNotNull('email_verified_at')
                ->lockForUpdate()
                ->first();

            if (! $user || ! hash_equals($this->identity->normalizeEmail($user->email), $email)) {
                return false;
            }

            if (Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => ['Password baru harus berbeda dari password saat ini.']]);
            }

            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            $reset->forceFill(['used_at' => now()])->save();
            PasswordResetRequest::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('id', '!=', $reset->id)
                ->update(['used_at' => now()]);
            $this->revocation->revoke($user);
            $this->log($user->id, 'password_reset_completed');

            return true;
        });

        if (! $completed) {
            throw ValidationException::withMessages(['token' => ['Token reset tidak valid atau telah kedaluwarsa.']]);
        }

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah.']);
    }

    private function log(int $userId, string $event): void
    {
        AccountActivationLog::create([
            'user_id' => $userId,
            'event' => $event,
            'ip_hash' => $this->identity->ipHash(request()->ip()),
            'user_agent_hash' => $this->identity->userAgentHash(request()->userAgent()),
            'created_at' => now(),
        ]);
    }
}
