<?php

namespace App\Http\Middleware;

use App\Models\ApplicantSession;
use App\Models\MemberApplication;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApplicant
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('member_onboarding.applications_enabled')) {
            return response()->json([
                'success' => false,
                'code' => 'MEMBER_APPLICATIONS_DISABLED',
                'message' => 'Layanan tidak tersedia.',
            ], 503);
        }

        if (! $request->hasSession()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $applicationId = (int) $request->session()->get('applicant_application_id', 0);
        $sessionMarker = (string) $request->session()->get('applicant_session_id', '');
        $session = ApplicantSession::query()
            ->where('session_id', $sessionMarker)
            ->where('member_application_id', $applicationId)
            ->first();

        $application = $applicationId > 0
            ? MemberApplication::with('approvedUser:id,id_anggota')->find($applicationId)
            : null;

        if (! $session || ! $application) {
            $request->session()->forget(['applicant_application_id', 'applicant_session_id']);

            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $session->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('applicant', $application);

        return $next($request);
    }
}
