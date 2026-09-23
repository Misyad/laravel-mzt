<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\AccountActivationLog;
use App\Models\AccountSetupEmailVerification;
use App\Models\User;
use App\Services\CredentialRevocationService;
use App\Services\MemberIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountSetupController extends Controller
{
    public function __construct(
        private MemberIdentityService $identity,
        private CredentialRevocationService $revocation
    ) {}

    public function sendEmail(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $user = $request->user();
        $this->requireSetup($user);
        $email = $this->identity->normalizeEmail($validated['email']);

        if (! $this->identity->emailAvailable($email, $user->id)) {
            return response()->json(['success' => true, 'message' => 'Jika email dapat digunakan, kode verifikasi telah dikirim.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($user, $email, $code) {
            AccountSetupEmailVerification::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);
            AccountSetupEmailVerification::create([
                'user_id' => $user->id,
                'email' => $email,
                'code_hash' => $this->identity->secretHash($code),
                'attempts_remaining' => (int) config('member_onboarding.email_verification.attempts', 5),
                'expires_at' => now()->addMinutes((int) config('member_onboarding.email_verification.ttl_minutes', 10)),
            ]);
            $this->log($user->id, 'setup_email_sent');
        });

        try {
            Mail::to($email)->send(new VerificationCodeMail($code, 'Verifikasi email akun MZT'));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json(['success' => true, 'message' => 'Jika email dapat digunakan, kode verifikasi telah dikirim.']);
    }

    public function verifyEmail(Request $request)
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        $this->requireSetup($user);

        $verified = DB::transaction(function () use ($user, $validated) {
            $verification = AccountSetupEmailVerification::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $verification || $verification->expires_at->isPast() || $verification->attempts_remaining <= 0) {
                return false;
            }

            if (! hash_equals($verification->code_hash, $this->identity->secretHash($validated['code']))) {
                $verification->decrement('attempts_remaining');
                if ($verification->fresh()->attempts_remaining <= 0) {
                    $verification->forceFill(['consumed_at' => now()])->save();
                }
                $this->log($user->id, 'setup_email_verification_failed');

                return false;
            }

            $verification->forceFill(['verified_at' => now()])->save();
            $this->log($user->id, 'setup_email_verified');

            return true;
        });

        if (! $verified) {
            throw ValidationException::withMessages(['code' => ['Kode verifikasi tidak valid atau telah kedaluwarsa.']]);
        }

        return response()->json(['success' => true, 'message' => 'Email berhasil diverifikasi.']);
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ]);

        if ($validated['password'] === 'mzt12345') {
            throw ValidationException::withMessages(['password' => ['Password baru tidak boleh menggunakan password sementara.']]);
        }

        if (! $this->revocation->sessionsAtomicallyRevocable()) {
            return response()->json([
                'success' => false,
                'code' => 'SESSION_REVOCATION_UNAVAILABLE',
                'message' => 'Pengaturan akun sementara tidak tersedia.',
            ], 503);
        }

        $caller = $request->user();
        $preserveSession = Auth::guard('web')->check() && $request->hasSession();
        $sessionId = $preserveSession ? $request->session()->getId() : null;
        $verificationEmail = AccountSetupEmailVerification::query()
            ->where('user_id', $caller->id)
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->value('email');

        try {
            DB::transaction(function () use ($caller, $validated, $sessionId, $verificationEmail) {
                if ($verificationEmail !== null) {
                    $this->identity->lockEmail($verificationEmail);
                }
                $user = User::whereKey($caller->id)->lockForUpdate()->firstOrFail();
                $this->requireSetup($user);

                if (! Hash::check($validated['current_password'], $user->password)) {
                    throw ValidationException::withMessages(['current_password' => ['Password saat ini salah.']]);
                }

                if (Hash::check($validated['password'], $user->password)) {
                    throw ValidationException::withMessages(['password' => ['Password baru harus berbeda dari password saat ini.']]);
                }

                $verification = AccountSetupEmailVerification::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('verified_at')
                    ->whereNull('consumed_at')
                    ->where('expires_at', '>', now())
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $verification) {
                    throw ValidationException::withMessages(['email' => ['Verifikasi email diperlukan.']]);
                }

                if ($verificationEmail === null || ! hash_equals($verificationEmail, $verification->email)) {
                    throw ValidationException::withMessages(['email' => ['Verifikasi email diperlukan.']]);
                }
                if (! $this->identity->emailAvailable($verification->email, $user->id)) {
                    throw ValidationException::withMessages(['email' => ['Verifikasi email diperlukan.']]);
                }

                $user->forceFill([
                    'email' => $verification->email,
                    'email_verified_at' => now(),
                    'password' => Hash::make($validated['password']),
                    'password_changed_at' => now(),
                    'account_claimed_at' => now(),
                    'account_setup_required' => false,
                    'remember_token' => Str::random(60),
                ])->save();
                $verification->forceFill(['consumed_at' => now()])->save();
                $this->revocation->revoke($user, $sessionId);
                $caller->setRawAttributes($user->getAttributes(), true);
                $this->log($user->id, 'account_setup_completed');
            });
        } catch (QueryException $exception) {
            if ($this->isEmailUniqueViolation($exception)) {
                throw ValidationException::withMessages(['email' => ['Verifikasi email diperlukan.']]);
            }

            throw $exception;
        }

        if ($preserveSession) {
            $request->session()->regenerate();
            $request->session()->put('password_hash_web', $caller->getAuthPassword());
        }

        return response()->json(['success' => true, 'message' => 'Pengaturan akun selesai.']);
    }

    private function requireSetup(User $user): void
    {
        if (! (bool) $user->account_setup_required || $user->account_claimed_at !== null) {
            throw ValidationException::withMessages(['account' => ['Akun tidak memerlukan pengaturan.']]);
        }
    }

    private function isEmailUniqueViolation(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['19', '1062', '23000', '23505'], true)) {
            return false;
        }

        return str_contains(strtolower($exception->getMessage()), 'email');
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
