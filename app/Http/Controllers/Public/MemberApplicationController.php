<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\ApplicantSession;
use App\Models\MemberApplication;
use App\Models\MemberApplicationEmailVerification;
use App\Models\MemberApplicationLog;
use App\Services\MemberIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberApplicationController extends Controller
{
    public function __construct(private MemberIdentityService $identity) {}

    public function store(Request $request)
    {
        if (! config('member_onboarding.applications_enabled')) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak tersedia.'], 503);
        }

        $this->requireStatefulSession($request);
        $this->normalizeYearInputs($request);
        $validated = $request->validate(array_merge($this->profileRules(), [
            'submission_token' => ['required', 'uuid'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], $this->prohibitedRules()));
        $validated['tahun_masuk'] = $this->canonicalYearDate($validated['tahun_masuk']);
        $validated['tahun_keluar'] = $this->canonicalYearDate($validated['tahun_keluar']);
        $this->validateYearOrder($validated['tahun_masuk'], $validated['tahun_keluar']);
        $email = $this->identity->normalizeEmail($validated['email']);
        $submissionKeyHash = $this->identity->secretHash($validated['submission_token']);
        $existing = MemberApplication::where('submission_key_hash', $submissionKeyHash)->first();
        if ($existing) {
            $this->establishSession($request, $existing);

            return response()->json([
                'success' => true,
                'data' => [
                    'application_number' => $existing->application_number,
                    'application' => $this->payload($existing),
                ],
            ]);
        }

        if (! $this->identity->emailAvailable($email)) {
            throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
        }

        $foto = $request->file('foto')->store('image/member-applications', 'public');
        if (! is_string($foto) || $foto === '') {
            throw new \RuntimeException('PHOTO_STORAGE_FAILED');
        }
        $code = $this->newCode();
        $created = false;

        try {
            $application = DB::transaction(function () use ($request, $validated, $email, $foto, $code, $submissionKeyHash, &$created) {
                DB::table('member_submission_locks')->insertOrIgnore([
                    'submission_key_hash' => $submissionKeyHash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('member_submission_locks')->where('submission_key_hash', $submissionKeyHash)->lockForUpdate()->first();
                $this->identity->lockEmail($email);
                $existing = MemberApplication::where('submission_key_hash', $submissionKeyHash)->lockForUpdate()->first();
                if ($existing) {
                    Storage::disk('public')->delete($foto);
                    $this->establishSession($request, $existing);

                    return $existing;
                }
                if (! $this->identity->emailAvailable($email)) {
                    throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
                }

                $uuid = (string) Str::uuid();
                $application = MemberApplication::create([
                    'uuid' => $uuid,
                    'application_number' => 'APP-'.strtoupper(str_replace('-', '', $uuid)),
                    'submission_key_hash' => $submissionKeyHash,
                    'name' => trim($validated['name']),
                    'normalized_name' => $this->identity->normalizeText($validated['name']),
                    'email' => $email,
                    'active_email' => $email,
                    'no_hp' => trim($validated['no_hp']),
                    'normalized_phone' => $this->identity->normalizePhone($validated['no_hp']),
                    'alamat' => trim($validated['alamat']),
                    'pekerjaan' => trim($validated['pekerjaan']),
                    'niqobah' => trim($validated['niqobah']),
                    'tempat_lahir' => trim($validated['tempat_lahir']),
                    'tanggal_lahir' => $validated['tanggal_lahir'],
                    'tahun_masuk' => $validated['tahun_masuk'],
                    'tahun_keluar' => $validated['tahun_keluar'],
                    'foto' => $foto,
                    'status' => MemberApplication::PENDING_EMAIL,
                ]);
                $this->issueCode($application, $code);
                $this->log($application, 'application_created', null, MemberApplication::PENDING_EMAIL, 'applicant');
                $this->establishSession($request, $application);
                $created = true;

                return $application;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($foto);
            if ($exception instanceof QueryException && $this->isActiveEmailUniqueViolation($exception)) {
                throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
            }

            throw $exception;
        }

        if ($created) {
            $this->sendVerificationMail($email, $code);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'application_number' => $application->application_number,
                'application' => $this->payload($application),
            ],
        ], $created ? 201 : 200);
    }

    public function resend(Request $request)
    {
        $application = $this->application($request);
        if ($application->status !== MemberApplication::PENDING_EMAIL) {
            throw ValidationException::withMessages(['status' => ['Email pendaftaran sudah diverifikasi.']]);
        }

        $code = $this->newCode();
        DB::transaction(function () use ($application, $code) {
            $this->issueCode($application, $code);
            $this->log($application, 'verification_code_resent', $application->status, $application->status, 'applicant');
        });
        $this->sendVerificationMail($application->email, $code);

        return response()->json(['success' => true, 'data' => ['application' => $this->payload($application->fresh())]]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $application = $this->application($request);

        $verified = DB::transaction(function () use ($application, $validated) {
            $locked = MemberApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== MemberApplication::PENDING_EMAIL) {
                return $locked->status === MemberApplication::SUBMITTED;
            }

            $verification = MemberApplicationEmailVerification::query()
                ->where('member_application_id', $locked->id)
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
                $this->log($locked, 'email_verification_failed', $locked->status, $locked->status, 'applicant');

                return false;
            }

            $oldStatus = $locked->status;
            $locked->forceFill([
                'status' => MemberApplication::SUBMITTED,
                'email_verified_at' => now(),
            ])->save();
            $verification->forceFill(['verified_at' => now(), 'consumed_at' => now()])->save();
            $this->log($locked, 'email_verified', $oldStatus, MemberApplication::SUBMITTED, 'applicant');

            return true;
        });

        if (! $verified) {
            throw ValidationException::withMessages(['code' => ['Kode verifikasi tidak valid atau telah kedaluwarsa.']]);
        }

        return response()->json(['success' => true, 'data' => ['application' => $this->payload($application->fresh())]]);
    }

    public function update(Request $request)
    {
        $application = $this->application($request);
        if (! in_array($application->status, [MemberApplication::PENDING_EMAIL, MemberApplication::SUBMITTED, MemberApplication::REJECTED], true)) {
            return response()->json(['success' => false, 'message' => 'Pendaftaran tidak dapat diubah.'], 409);
        }

        $this->normalizeYearInputs($request);
        $rules = [];
        foreach ($this->profileRules() as $field => $rule) {
            $rules[$field] = array_merge(['sometimes'], array_slice($rule, 1));
        }
        $rules['foto'] = ['sometimes', 'image', 'mimes:jpg,jpeg,png', 'max:5120'];
        $validated = $request->validate(array_merge($rules, $this->prohibitedRules()));
        foreach (['tahun_masuk', 'tahun_keluar'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = $this->canonicalYearDate($validated[$field]);
            }
        }
        $this->validateYearOrder(
            $validated['tahun_masuk'] ?? $application->tahun_masuk->format('Y-m-d'),
            $validated['tahun_keluar'] ?? $application->tahun_keluar->format('Y-m-d')
        );
        $newEmail = array_key_exists('email', $validated)
            ? $this->identity->normalizeEmail($validated['email'])
            : $application->email;
        $emailChanged = ! hash_equals($application->email, $newEmail);
        $resubmittingRejected = $application->status === MemberApplication::REJECTED;

        if (($emailChanged || $resubmittingRejected) && ! $this->identity->emailAvailable($newEmail, null, $application->id)) {
            throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
        }

        $code = $emailChanged ? $this->newCode() : null;
        $newFoto = null;
        $oldFoto = null;

        try {
            $updated = DB::transaction(function () use ($request, $application, $validated, $newEmail, $emailChanged, $resubmittingRejected, $code, &$newFoto, &$oldFoto) {
                if ($emailChanged || $resubmittingRejected) {
                    $this->identity->lockEmail($newEmail);
                }
                $locked = MemberApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                $oldFoto = $locked->foto;
                if (! in_array($locked->status, [MemberApplication::PENDING_EMAIL, MemberApplication::SUBMITTED, MemberApplication::REJECTED], true)) {
                    abort(409, 'Pendaftaran tidak dapat diubah.');
                }
                if (($emailChanged || $resubmittingRejected) && ! $this->identity->emailAvailable($newEmail, null, $locked->id)) {
                    throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
                }

                $attributes = [];
                foreach (['name', 'no_hp', 'alamat', 'pekerjaan', 'niqobah', 'tempat_lahir', 'tanggal_lahir', 'tahun_masuk', 'tahun_keluar'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $attributes[$field] = is_string($validated[$field]) ? trim($validated[$field]) : $validated[$field];
                    }
                }
                if ($request->hasFile('foto')) {
                    $newFoto = $request->file('foto')->store('image/member-applications', 'public');
                    if (! is_string($newFoto) || $newFoto === '') {
                        throw new \RuntimeException('PHOTO_STORAGE_FAILED');
                    }
                    $attributes['foto'] = $newFoto;
                }
                $attributes['normalized_name'] = $this->identity->normalizeText($attributes['name'] ?? $locked->name);
                $attributes['normalized_phone'] = $this->identity->normalizePhone($attributes['no_hp'] ?? $locked->no_hp);
                if ($resubmittingRejected) {
                    $attributes['active_email'] = $newEmail;
                    $attributes['status'] = MemberApplication::SUBMITTED;
                    $attributes['reviewed_by'] = null;
                    $attributes['under_review_at'] = null;
                    $attributes['rejected_at'] = null;
                    $attributes['rejection_reason'] = null;
                }
                if ($emailChanged) {
                    $attributes['email'] = $newEmail;
                    $attributes['active_email'] = $newEmail;
                    $attributes['email_verified_at'] = null;
                    $attributes['status'] = MemberApplication::PENDING_EMAIL;
                }

                $oldStatus = $locked->status;
                $locked->forceFill($attributes)->save();
                if ($emailChanged) {
                    $this->issueCode($locked, $code);
                }
                $this->log($locked, 'application_updated', $oldStatus, $locked->status, 'applicant', [
                    'email_changed' => $emailChanged,
                ]);

                return $locked;
            });
        } catch (\Throwable $exception) {
            if ($newFoto) {
                Storage::disk('public')->delete($newFoto);
            }
            if ($exception instanceof QueryException && $this->isActiveEmailUniqueViolation($exception)) {
                throw ValidationException::withMessages(['email' => ['Email tidak dapat digunakan.']]);
            }

            throw $exception;
        }

        if ($newFoto && $oldFoto !== $newFoto) {
            Storage::disk('public')->delete($oldFoto);
        }
        if ($emailChanged) {
            $this->sendVerificationMail($newEmail, $code);
        }

        return response()->json(['success' => true, 'data' => ['application' => $this->payload($updated)]]);
    }

    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'no_hp' => ['required', 'string', 'max:40'],
            'alamat' => ['required', 'string', 'max:2000'],
            'pekerjaan' => ['required', 'string', 'max:255'],
            'niqobah' => ['required', 'string', 'max:255'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before:today'],
            'tahun_masuk' => ['required', 'date_format:Y-m-d'],
            'tahun_keluar' => ['required', 'date_format:Y-m-d'],
        ];
    }

    private function prohibitedRules(): array
    {
        return [
            'id' => ['prohibited'],
            'uuid' => ['prohibited'],
            'application_number' => ['prohibited'],
            'status' => ['prohibited'],
            'id_anggota' => ['prohibited'],
            'roles' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'approved_user_id' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ];
    }

    private function normalizeYearInputs(Request $request): void
    {
        $years = [];
        foreach (['tahun_masuk', 'tahun_keluar'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $years[$field] = $this->normalizeYearDate($request->input($field));
            }
        }
        if ($years !== []) {
            $request->merge($years);
        }
    }

    private function normalizeYearDate(string $value): string
    {
        return preg_match('/^\d{4}$/', $value) ? $value.'-01-01' : $value;
    }

    private function canonicalYearDate(string $value): string
    {
        return substr($value, 0, 4).'-01-01';
    }

    private function validateYearOrder(string $entryDate, string $exitDate): void
    {
        if ($exitDate < $entryDate) {
            throw ValidationException::withMessages([
                'tahun_keluar' => ['Tahun keluar harus sama atau setelah tahun masuk.'],
            ]);
        }
    }

    private function issueCode(MemberApplication $application, string $code): void
    {
        MemberApplicationEmailVerification::query()
            ->where('member_application_id', $application->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
        MemberApplicationEmailVerification::create([
            'member_application_id' => $application->id,
            'email' => $application->email,
            'code_hash' => $this->identity->secretHash($code),
            'attempts_remaining' => (int) config('member_onboarding.email_verification.attempts', 5),
            'expires_at' => now()->addMinutes((int) config('member_onboarding.email_verification.ttl_minutes', 10)),
        ]);
    }

    private function establishSession(Request $request, MemberApplication $application): void
    {
        $request->session()->regenerate();
        $sessionMarker = Str::random(64);
        $request->session()->put([
            'applicant_application_id' => $application->id,
            'applicant_session_id' => $sessionMarker,
        ]);
        ApplicantSession::create([
            'session_id' => $sessionMarker,
            'member_application_id' => $application->id,
            'created_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function requireStatefulSession(Request $request): void
    {
        if (! $request->hasSession() || ! $request->attributes->get('sanctum')) {
            abort(419, 'Stateful browser session required.');
        }
    }

    private function application(Request $request): MemberApplication
    {
        return $request->attributes->get('applicant');
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendVerificationMail(string $email, string $code): void
    {
        try {
            Mail::to($email)->send(new VerificationCodeMail($code, 'Verifikasi email pendaftaran anggota MZT'));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function isActiveEmailUniqueViolation(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['19', '1062', '23000', '23505'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'active_email')
            || (str_contains($message, 'member_applications') && str_contains($message, 'email'));
    }

    private function payload(MemberApplication $application): array
    {
        return Arr::only($application->toArray(), [
            'uuid', 'application_number', 'name', 'email', 'no_hp', 'alamat', 'pekerjaan', 'niqobah',
            'tempat_lahir', 'tanggal_lahir', 'tahun_masuk', 'tahun_keluar', 'foto', 'status',
            'email_verified_at', 'approved_user_id', 'id_anggota', 'approved_at', 'rejected_at', 'rejection_reason',
            'created_at', 'updated_at',
        ]);
    }

    private function log(MemberApplication $application, string $event, ?string $oldStatus, ?string $newStatus, string $source, array $metadata = []): void
    {
        MemberApplicationLog::create([
            'member_application_id' => $application->id,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'source' => $source,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
