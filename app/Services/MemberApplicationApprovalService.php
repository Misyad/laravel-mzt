<?php

namespace App\Services;

use App\Mail\MemberApplicationApprovedMail;
use App\Models\ApplicantSession;
use App\Models\DataUser;
use App\Models\HakAksesRole;
use App\Models\MemberApplication;
use App\Models\MemberApplicationLog;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MemberApplicationApprovalService
{
    public function __construct(private MemberIdentityService $identity) {}

    public function approve(MemberApplication $application, User $approver): array
    {
        $barcodePath = null;
        $photoPath = null;
        $email = MemberApplication::whereKey($application->id)->value('email');
        $claimToken = Str::random(64);

        try {
            return DB::transaction(function () use ($application, $approver, $email, $claimToken, &$barcodePath, &$photoPath) {
                $this->identity->lockEmail($email);
                $locked = MemberApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                if (! hash_equals($email, $locked->email)) {
                    throw new RuntimeException('EMAIL_NOT_AVAILABLE');
                }
                if (! in_array($locked->status, [MemberApplication::SUBMITTED, MemberApplication::UNDER_REVIEW], true)) {
                    throw new RuntimeException('INVALID_APPLICATION_STATUS');
                }
                if ($locked->email_verified_at === null) {
                    throw new RuntimeException('EMAIL_NOT_VERIFIED');
                }
                if (! $this->identity->emailAvailable($locked->email, null, $locked->id)) {
                    throw new RuntimeException('EMAIL_NOT_AVAILABLE');
                }

                $idAnggota = $this->nextId((int) now()->format('Y'));
                if (User::where('id_anggota', $idAnggota)->exists()) {
                    throw new RuntimeException('MEMBER_ID_COLLISION');
                }

                $photoPath = 'image/anggota/'.Str::uuid().'.'.pathinfo($locked->foto, PATHINFO_EXTENSION);
                $photo = Storage::disk('public')->get($locked->foto);
                if (! Storage::disk('public')->put($photoPath, $photo)) {
                    throw new RuntimeException('PHOTO_STORAGE_FAILED');
                }

                $barcodePath = 'image/barcode/barcode-'.$idAnggota.'.png';
                $barcode = base64_decode(\DNS1D::getBarcodePNG($idAnggota, 'C39'), true);
                if ($barcode === false || ! Storage::disk('public')->put($barcodePath, $barcode)) {
                    throw new RuntimeException('BARCODE_STORAGE_FAILED');
                }

                try {
                    $user = User::create([
                        'id_anggota' => $idAnggota,
                        'name' => $locked->name,
                        'email' => $locked->email,
                        'email_verified_at' => $locked->email_verified_at,
                        'password' => Hash::make(Str::random(64)),
                        'is_active' => '1',
                        'password_changed_at' => null,
                        'account_setup_required' => true,
                        'account_claimed_at' => null,
                    ]);
                } catch (QueryException $exception) {
                    if ($this->isUniqueConstraintViolation($exception)) {
                        throw new RuntimeException(
                            str_contains(strtolower($exception->getMessage()), 'email')
                                ? 'EMAIL_NOT_AVAILABLE'
                                : 'MEMBER_ID_COLLISION'
                        );
                    }

                    throw $exception;
                }

                DataUser::create([
                    'id_users' => $user->id,
                    'no_hp' => $locked->no_hp,
                    'barcode' => $barcodePath,
                    'alamat' => $locked->alamat,
                    'pekerjaan' => $locked->pekerjaan,
                    'niqobah' => $locked->niqobah,
                    'tempat_lahir' => $locked->tempat_lahir,
                    'tanggal_lahir' => $locked->tanggal_lahir,
                    'tahun_masuk' => $locked->tahun_masuk,
                    'tahun_keluar' => $locked->tahun_keluar,
                    'foto' => $photoPath,
                    'is_active' => '1',
                ]);

                foreach (['anggota', 'profil'] as $role) {
                    HakAksesRole::create([
                        'id_users' => $user->id,
                        'nama_role' => $role,
                        'hak_akses' => 'access',
                    ]);
                }

                PasswordResetRequest::query()
                    ->where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->update(['used_at' => now()]);
                PasswordResetRequest::create([
                    'token_hash' => $this->identity->secretHash($claimToken),
                    'user_id' => $user->id,
                    'email' => $locked->email,
                    'expires_at' => now()->addMinutes((int) config('member_onboarding.password_reset.ttl_minutes', 30)),
                    'created_at' => now(),
                ]);

                $oldStatus = $locked->status;
                $locked->forceFill([
                    'status' => MemberApplication::APPROVED,
                    'active_email' => null,
                    'approved_user_id' => $user->id,
                    'reviewed_by' => $approver->id,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ])->save();
                ApplicantSession::where('member_application_id', $locked->id)->delete();
                MemberApplicationLog::create([
                    'member_application_id' => $locked->id,
                    'event' => 'application_approved',
                    'old_status' => $oldStatus,
                    'new_status' => MemberApplication::APPROVED,
                    'actor_user_id' => $approver->id,
                    'source' => 'admin',
                    'metadata' => ['approved_user_id' => $user->id, 'id_anggota' => $idAnggota],
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($locked, $idAnggota, $claimToken) {
                    try {
                        Mail::to($locked->email)->send(new MemberApplicationApprovedMail(
                            $idAnggota,
                            $locked->application_number,
                            $claimToken,
                            $locked->email
                        ));
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });

                return [$locked, $user];
            });
        } catch (\Throwable $exception) {
            if ($barcodePath) {
                Storage::disk('public')->delete($barcodePath);
            }
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['1062', '23000', '23505'], true);
    }

    private function nextId(int $year): string
    {
        DB::table('member_id_counters')->insertOrIgnore([
            'year' => $year,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('member_id_counters')->where('year', $year)->lockForUpdate()->first();
        $number = (int) $counter->next_number;

        do {
            if ($number > 999999) {
                throw new RuntimeException('MEMBER_ID_SEQUENCE_EXHAUSTED');
            }
            $idAnggota = sprintf('%04d%06d', $year, $number);
            $number++;
        } while (User::where('id_anggota', $idAnggota)->exists());

        DB::table('member_id_counters')->where('year', $year)->update([
            'next_number' => $number,
            'updated_at' => now(),
        ]);

        return $idAnggota;
    }
}
