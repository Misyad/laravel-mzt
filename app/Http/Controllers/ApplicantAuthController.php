<?php

namespace App\Http\Controllers;

use App\Models\ApplicantSession;
use App\Models\MemberApplication;
use App\Services\MemberIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicantAuthController extends Controller
{
    public function __construct(private MemberIdentityService $identity) {}

    public function login(Request $request)
    {
        if (! config('member_onboarding.applications_enabled')) {
            return response()->json([
                'success' => false,
                'code' => 'MEMBER_APPLICATIONS_DISABLED',
                'message' => 'Layanan tidak tersedia.',
            ], 503);
        }

        if (! $request->hasSession() || ! $request->attributes->get('sanctum')) {
            abort(419, 'Stateful browser session required.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'application_number' => ['required', 'string', 'max:40'],
        ]);
        $email = $this->identity->normalizeEmail($validated['email']);
        $candidate = MemberApplication::query()
            ->with('approvedUser:id,id_anggota,is_active')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->where('application_number', strtoupper(trim($validated['application_number'])))
            ->first();

        if (! $candidate) {
            throw ValidationException::withMessages(['email' => ['Email atau nomor pendaftaran salah.']]);
        }

        $sessionMarker = str()->random(64);
        $application = DB::transaction(function () use ($candidate, $email, $validated, $sessionMarker) {
            $application = MemberApplication::query()->whereKey($candidate->id)->lockForUpdate()->first();
            $valid = $application
                && hash_equals($this->identity->normalizeEmail($application->email), $email)
                && hash_equals($application->application_number, strtoupper(trim($validated['application_number'])));

            if (! $valid) {
                throw ValidationException::withMessages(['email' => ['Email atau nomor pendaftaran salah.']]);
            }

            ApplicantSession::create([
                'session_id' => $sessionMarker,
                'member_application_id' => $application->id,
                'created_at' => now(),
                'last_seen_at' => now(),
            ]);

            return $application;
        });

        $request->session()->regenerate();
        $request->session()->put([
            'applicant_application_id' => $application->id,
            'applicant_session_id' => $sessionMarker,
        ]);

        return response()->json(['success' => true, 'data' => ['application' => $this->payload($application)]]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['application' => $this->payload($request->attributes->get('applicant'))],
        ]);
    }

    public function logout(Request $request)
    {
        ApplicantSession::where('session_id', $request->session()->get('applicant_session_id'))->delete();
        $request->session()->forget(['applicant_application_id', 'applicant_session_id']);
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'data' => ['application' => null]]);
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
}
