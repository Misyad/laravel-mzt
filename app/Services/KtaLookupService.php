<?php

namespace App\Services;

use App\Models\DataUser;
use App\Models\User;
use App\Support\KtaMasker;
use Illuminate\Support\Carbon;

/**
 * Read-only lookup engine for the public "Cek Status KTA" flow.
 *
 * Contract (enforced by the controller, reflected here as return arrays):
 *  - exact match only — no LIKE / wildcard / fuzzy matching
 *  - date of birth sentinels are rejected before touching the DB
 *  - the public response NEVER exposes candidate counts; the real state is
 *    returned internally and stored in the challenge token by the controller
 *  - all identity output is masked (KtaMasker)
 *
 * This service performs no writes and never logs raw PII.
 */
class KtaLookupService
{
    /**
     * Internal match outcomes (server-side only, never serialised publicly).
     */
    public const MATCH_NONE = 'none';
    public const MATCH_SINGLE = 'single';
    public const MATCH_MULTIPLE = 'multiple';

    /**
     * Resolve a lookup request into an internal state.
     *
     * @return array{
     *     match: string,
     *     candidate_ids: array<int, int>,
     *     verify_method: string,
     *     disambiguate_index: int,
     *     attempts_left: int
     * }
     */
    public function resolve(string $mode, ?string $name, ?string $dob, ?string $memberId): array
    {
        if ($mode === 'member_id') {
            return $this->resolveByMemberId($memberId);
        }

        return $this->resolveByNameDob($name, $dob);
    }

    /**
     * Mode B — exact member number. Unique by production audit (0 duplicates),
     * but ownership must still be proven afterwards, so the result never
     * opens the status on its own.
     */
    protected function resolveByMemberId(?string $memberId): array
    {
        $id = KtaMasker::normalizeMemberId($memberId);

        $user = User::query()
            ->where('id_anggota', $id)
            ->first(['id']);

        return $this->stateFromUsers($user ? [$user->id] : []);
    }

    /**
     * Mode A — exact, case-insensitive name + date of birth.
     */
    protected function resolveByNameDob(?string $name, ?string $dob): array
    {
        $normalizedName = KtaMasker::normalizeName($name);
        $date = $this->normalizeDob($dob);

        if ($normalizedName === '' || $date === null) {
            return $this->stateFromUsers([]);
        }

        // Exact match on the stored name (case-insensitive) + exact DOB.
        // The name comparison is done via LOWER(TRIM(name)) = ? so it stays an
        // equality predicate — no wildcards, no prefix search.
        $ids = User::query()
            ->join('data_users', 'data_users.id_users', '=', 'users.id')
            ->whereRaw('LOWER(TRIM(users.name)) = ?', [$normalizedName])
            ->whereDate('data_users.tanggal_lahir', $date)
            ->pluck('users.id')
            ->all();

        return $this->stateFromUsers($ids);
    }

    /**
     * Build the internal state from the matched user ids.
     *
     * @param  array<int, int>  $ids
     */
    protected function stateFromUsers(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (count($ids) === 0) {
            return [
                'match' => self::MATCH_NONE,
                'candidate_ids' => [],
                'verify_method' => 'hp_last4',
                'disambiguate_index' => 0,
                'attempts_left' => (int) config('kta.max_attempts', 5),
            ];
        }

        if (count($ids) > 1) {
            return [
                'match' => self::MATCH_MULTIPLE,
                'candidate_ids' => $ids,
                'verify_method' => 'disambiguate',
                'disambiguate_index' => 0,
                'attempts_left' => (int) config('kta.max_attempts', 5),
            ];
        }

        $candidateId = $ids[0];

        return [
            'match' => self::MATCH_SINGLE,
            'candidate_ids' => [$candidateId],
            'verify_method' => $this->preferredVerifyMethod($candidateId),
            'disambiguate_index' => 0,
            'attempts_left' => (int) config('kta.max_attempts', 5),
        ];
    }

    /**
     * Ownership challenge for a single candidate: HP last-4 when a phone
     * exists, otherwise the two-field fallback (tahun_masuk + tempat_lahir).
     */
    public function preferredVerifyMethod(int $userId): string
    {
        $data = DataUser::query()
            ->where('id_users', $userId)
            ->first(['no_hp']);

        return KtaMasker::phoneLast4($data?->no_hp) !== null
            ? 'hp_last4'
            : 'no_hp_fallback';
    }

    /**
     * Verify ownership for a candidate.
     *
     * @param  array<string, mixed>  $answer
     * @return bool true when the answer proves ownership
     */
    public function verifyOwnership(int $userId, string $method, array $answer): bool
    {
        $data = DataUser::query()
            ->where('id_users', $userId)
            ->first(['no_hp', 'tahun_masuk', 'tempat_lahir']);

        if (! $data) {
            return false;
        }

        if ($method === 'hp_last4') {
            $expected = KtaMasker::phoneLast4($data->no_hp);
            $given = preg_replace('/\D+/', '', (string) ($answer['value'] ?? '')) ?? '';

            return $expected !== null && $given !== '' && hash_equals($expected, $given);
        }

        if ($method === 'no_hp_fallback') {
            // Two independent fields must BOTH match — a single field is never
            // treated as proof of ownership.
            $tahunMasuk = trim((string) ($answer['tahun_masuk'] ?? ''));
            $tempatLahir = KtaMasker::normalizeName($answer['tempat_lahir'] ?? null);

            $expectedTahun = $this->normalizeYear($data->tahun_masuk);
            $expectedTempat = KtaMasker::normalizeName($data->tempat_lahir);

            return $expectedTahun !== null
                && $expectedTempat !== ''
                && hash_equals($expectedTahun, $this->normalizeYear($tahunMasuk) ?? '')
                && hash_equals($expectedTempat, $tempatLahir);
        }

        return false;
    }

    /**
     * Apply a disambiguation answer against the remaining candidate pool.
     *
     * @param  array<int, int>  $candidateIds
     * @return array<int, int> narrowed candidate ids
     */
    public function narrowByDisambiguation(array $candidateIds, string $field, ?string $value): array
    {
        $field = trim($field);
        if (! in_array($field, config('kta.disambiguate', []), true)) {
            return [];
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $rows = DataUser::query()
            ->whereIn('id_users', $candidateIds)
            ->get(['id_users', 'tahun_masuk', 'tempat_lahir', 'niqobah']);

        return $rows->filter(function (DataUser $row) use ($field, $value): bool {
            return match ($field) {
                'tahun_masuk' => $this->normalizeYear($row->tahun_masuk) === $this->normalizeYear($value),
                'tempat_lahir' => KtaMasker::normalizeName($row->tempat_lahir) === KtaMasker::normalizeName($value),
                'niqobah' => KtaMasker::normalizeName($row->niqobah) === KtaMasker::normalizeName($value),
                default => false,
            };
        })->pluck('id_users')->map('intval')->values()->all();
    }

    /**
     * Build the masked public payload for a verified candidate.
     *
     * Only these fields may ever leave the server: masked name, masked member
     * number, active flag, QR payload (member number) and the physical-card
     * marker (always `not_tracked` until a print table exists).
     *
     * @return array<string, mixed>
     */
    public function publicResult(int $userId): array
    {
        $user = User::query()
            ->where('id', $userId)
            ->first(['id', 'name', 'id_anggota', 'is_active']);

        if (! $user) {
            return [];
        }

        return [
            'verified' => true,
            'nama_masked' => KtaMasker::name($user->name),
            'id_anggota_masked' => KtaMasker::memberId($user->id_anggota),
            'status' => ((string) $user->is_active === '1') ? 'active' : 'non_active',
            'qr_payload' => (string) $user->id_anggota,
            'kta' => [
                'type' => 'digital',
                'fisik' => (string) config('kta.physical_status', 'not_tracked'),
            ],
        ];
    }

    /**
     * Validate + normalise a date of birth. Returns null when the input is
     * empty, malformed, a known sentinel, or outside the accepted range.
     */
    public function normalizeDob(?string $dob): ?string
    {
        $dob = trim((string) $dob);
        if ($dob === '') {
            return null;
        }

        if (in_array($dob, config('kta.dob.sentinels', []), true)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $dob)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        if ($date->format('Y-m-d') !== $dob) {
            return null;
        }

        if ($date->lt(Carbon::parse(config('kta.dob.min', '1900-01-01')))) {
            return null;
        }

        if ($date->gt(Carbon::now()->endOfDay())) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Normalise a year-ish value ("2011", "2011-07-12") to a 4-digit year.
     */
    protected function normalizeYear(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d{4})/', $value, $m) !== 1) {
            return null;
        }

        return $m[1];
    }
}
