<?php

namespace App\Http\Controllers;

use App\Models\MemberApplication;
use App\Models\MemberApplicationLog;
use App\Models\User;
use App\Services\MemberApplicationApprovalService;
use App\Services\MemberIdentityService;
use App\Support\MemberManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class MemberApplicationAdminController extends Controller
{
    public function __construct(
        private MemberApplicationApprovalService $approval,
        private MemberIdentityService $identity
    ) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending_email,submitted,under_review,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MemberApplication::query()->with('approvedUser:id,id_anggota')->latest('id');
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $applications = $query->limit((int) ($validated['per_page'] ?? 100))->get()
            ->map(fn (MemberApplication $application) => $this->withDuplicates($application));

        return response()->json(['success' => true, 'data' => ['applications' => $applications]]);
    }

    public function show(Request $request, string $uuid)
    {
        $this->authorizeAdmin($request);
        $application = MemberApplication::with(['logs', 'approvedUser:id,id_anggota'])->where('uuid', $uuid)->firstOrFail();

        return response()->json(['success' => true, 'data' => ['application' => $this->withDuplicates($application)]]);
    }

    public function underReview(Request $request, string $uuid)
    {
        $this->authorizeAdmin($request);
        $application = DB::transaction(function () use ($request, $uuid) {
            $application = MemberApplication::where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if ($application->status !== MemberApplication::SUBMITTED) {
                abort(409, 'Status pendaftaran tidak dapat diubah.');
            }

            $oldStatus = $application->status;
            $application->forceFill([
                'status' => MemberApplication::UNDER_REVIEW,
                'reviewed_by' => $request->user()->id,
                'under_review_at' => now(),
            ])->save();
            $this->log($application, 'application_under_review', $oldStatus, MemberApplication::UNDER_REVIEW, $request->user()->id);

            return $application;
        });

        return response()->json(['success' => true, 'data' => ['application' => $application]]);
    }

    public function approve(Request $request, string $uuid)
    {
        $this->authorizeAdmin($request);
        $application = MemberApplication::where('uuid', $uuid)->firstOrFail();

        try {
            [$application, $user] = $this->approval->approve($application, $request->user());
        } catch (RuntimeException $exception) {
            $messages = [
                'INVALID_APPLICATION_STATUS' => 'Status pendaftaran tidak dapat disetujui.',
                'EMAIL_NOT_VERIFIED' => 'Email pendaftar belum diverifikasi.',
                'EMAIL_NOT_AVAILABLE' => 'Email pendaftar sudah digunakan akun lain.',
                'MEMBER_ID_SEQUENCE_EXHAUSTED' => 'Urutan ID anggota tahun ini telah habis.',
                'MEMBER_ID_COLLISION' => 'ID anggota tidak dapat dialokasikan.',
                'PHOTO_STORAGE_FAILED' => 'Foto pendaftar tidak dapat disimpan.',
                'BARCODE_STORAGE_FAILED' => 'Barcode anggota tidak dapat disimpan.',
            ];

            return response()->json([
                'success' => false,
                'code' => $exception->getMessage(),
                'message' => $messages[$exception->getMessage()] ?? 'Pendaftaran tidak dapat disetujui.',
            ], 409);
        }

        $application->setRelation('approvedUser', $user);

        return response()->json([
            'success' => true,
            'data' => ['application' => $application, 'id_anggota' => $user->id_anggota],
        ]);
    }

    public function reject(Request $request, string $uuid)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $application = DB::transaction(function () use ($request, $validated, $uuid) {
            $application = MemberApplication::where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if (! in_array($application->status, [MemberApplication::SUBMITTED, MemberApplication::UNDER_REVIEW], true)) {
                abort(409, 'Status pendaftaran tidak dapat ditolak.');
            }

            $oldStatus = $application->status;
            $application->forceFill([
                'status' => MemberApplication::REJECTED,
                'active_email' => null,
                'reviewed_by' => $request->user()->id,
                'rejected_at' => now(),
                'rejection_reason' => trim($validated['reason']),
            ])->save();
            $this->log($application, 'application_rejected', $oldStatus, MemberApplication::REJECTED, $request->user()->id);

            return $application;
        });

        return response()->json(['success' => true, 'data' => ['application' => $application]]);
    }

    private function withDuplicates(MemberApplication $application): MemberApplication
    {
        $birthDate = $application->tanggal_lahir->format('Y-m-d');
        $phone = $this->identity->normalizePhone($application->no_hp);
        $members = User::query()
            ->join('data_users', 'users.id', '=', 'data_users.id_users')
            ->whereDate('data_users.tanggal_lahir', $birthDate)
            ->select(['users.id', 'users.id_anggota', 'users.name', 'data_users.tanggal_lahir', 'data_users.no_hp'])
            ->get()
            ->filter(fn ($member) => $this->identity->normalizeText($member->name) === $application->normalized_name)
            ->map(fn ($member) => [
                'id_users' => $member->id,
                'id_anggota' => $member->id_anggota,
                'name' => $member->name,
                'tanggal_lahir' => date('Y-m-d', strtotime((string) $member->tanggal_lahir)),
                'no_hp' => $member->no_hp,
                'match' => $phone !== '' && $this->identity->normalizePhone($member->no_hp) === $phone
                    ? 'name_birth_phone'
                    : 'name_birth',
            ])
            ->values();
        $applications = MemberApplication::query()
            ->whereKeyNot($application->id)
            ->where('normalized_name', $application->normalized_name)
            ->whereDate('tanggal_lahir', $birthDate)
            ->whereNotIn('status', [MemberApplication::APPROVED, MemberApplication::REJECTED])
            ->get()
            ->map(fn (MemberApplication $candidate) => [
                'application_number' => $candidate->application_number,
                'name' => $candidate->name,
                'tanggal_lahir' => $candidate->tanggal_lahir->format('Y-m-d'),
                'no_hp' => $candidate->no_hp,
                'status' => $candidate->status,
                'match' => $phone !== '' && $candidate->normalized_phone === $phone
                    ? 'name_birth_phone'
                    : 'name_birth',
            ]);

        return $application->setAttribute('possible_duplicates', $members->concat($applications)->values()->all());
    }

    private function authorizeAdmin(Request $request): void
    {
        Gate::forUser($request->user())->authorize('manageAccounts', MemberManagement::class);
    }

    private function log(MemberApplication $application, string $event, string $oldStatus, string $newStatus, int $actorId): void
    {
        MemberApplicationLog::create([
            'member_application_id' => $application->id,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'actor_user_id' => $actorId,
            'source' => 'admin',
            'created_at' => now(),
        ]);
    }
}
