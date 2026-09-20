<?php

namespace App\Http\Controllers;

use App\Enums\KtaPrintStatus;
use App\Models\DataUser;
use App\Models\KtaPrintRequest;
use App\Support\MemberManagement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KtaCardController extends Controller
{
    public function index(Request $request)
    {
        Gate::forUser($request->user())->authorize('viewCards', MemberManagement::class);

        $cards = DataUser::query()
            ->with('user:id,name,id_anggota,is_active')
            ->where('is_active', '1')
            ->whereHas('user', fn ($query) => $query->where('is_active', '1'))
            ->get()
            ->sortBy(fn (DataUser $member) => mb_strtolower((string) optional($member->user)->name))
            ->values()
            ->map(fn (DataUser $member) => [
                'id_users' => $member->id_users,
                'id_anggota' => (string) optional($member->user)->id_anggota,
                'nama' => (string) optional($member->user)->name,
            ]);

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }

    public function show(Request $request, $id)
    {
        Gate::forUser($request->user())->authorize('viewCards', MemberManagement::class);

        $member = $this->findActiveMember($id);
        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->cardData($member),
        ]);
    }

    public function fromPrintRequest(Request $request, $id)
    {
        $printRequest = KtaPrintRequest::find($id);
        if (! $printRequest) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan'], 404);
        }

        Gate::forUser($request->user())->authorize('viewCard', $printRequest);

        if (
            $printRequest->payment_status !== 'paid' ||
            ! in_array($printRequest->status, KtaPrintStatus::printableValues(), true)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'KTA belum dapat dicetak',
            ], 409);
        }

        $member = $this->findActiveMember($printRequest->id_users);
        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->cardData($member, $printRequest->id_anggota_snapshot),
        ]);
    }

    private function findActiveMember($id): ?DataUser
    {
        $member = DataUser::query()
            ->with('user:id,name,id_anggota,is_active')
            ->where('id_users', $id)
            ->where('is_active', '1')
            ->first();

        if (! $member || ! $member->user || (string) $member->user->is_active !== '1') {
            return null;
        }

        return $member;
    }

    private function cardData(DataUser $member, ?string $idAnggota = null): array
    {
        $idAnggota = $idAnggota ?: (string) $member->user->id_anggota;
        $barcode = \DNS1D::getBarcodeSVG($idAnggota, 'C39', 1, 40, 'black', false, true);

        return [
            'id_users' => $member->id_users,
            'id_anggota' => $idAnggota,
            'nama' => (string) $member->user->name,
            'alamat' => (string) ($member->alamat ?? ''),
            'niqobah' => (string) ($member->niqobah ?? ''),
            'tahun_masuk' => $this->year($member->tahun_masuk),
            'tahun_keluar' => $this->year($member->tahun_keluar),
            'foto' => $member->foto ?: null,
            'barcode_value' => $idAnggota,
            'barcode_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($barcode),
            'background_url' => '/assets/kta-background.jpg',
        ];
    }

    private function year($value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y') : null;
    }
}
