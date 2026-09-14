<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\KtaCheckRequest;
use App\Http\Requests\KtaVerifyRequest;
use App\Services\KtaChallengeService;
use App\Services\KtaLookupService;

/**
 * Public "Cek Status KTA" endpoints (PRD-derived, additive only).
 *
 *   POST /api/public/kta/check   — start a lookup
 *   POST /api/public/kta/verify  — prove ownership, then reveal status
 *
 * ANTI-ENUMERATION: the response to `check` is ALWAYS the same generic
 * `stage: challenge` shape for every input that passes format validation,
 * regardless of whether the lookup matched zero, one, or many members. The
 * real match state, candidate ids, disambiguation cursor and remaining
 * attempts live only inside the opaque challenge token. Only a successful
 * `verify` reveals anything, and even then only masked fields.
 *
 * This controller is read-only. It never touches existing controllers,
 * models, routes or tables.
 */
class KtaLookupController extends Controller
{
    public function __construct(
        protected KtaLookupService $lookup,
        protected KtaChallengeService $challenge,
    ) {
    }

    /**
     * Stage 1 — lookup. Generic response, no data leakage.
     */
    public function check(KtaCheckRequest $request)
    {
        if (! config('kta.enabled', true)) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak tersedia'], 503);
        }

        // Constant-time floor so single/not_found/multiple cannot be told
        // apart by latency (configurable; 0 in tests).
        $this->applyResponseDelay();

        $state = $this->lookup->resolve(
            (string) $request->input('mode'),
            $request->input('name'),
            $request->input('tanggal_lahir'),
            $request->input('id_anggota'),
        );

        $token = $this->challenge->issue(
            $state,
            $this->challenge->ipHash($request->ip()),
            $this->challenge->agentHash($request->userAgent()),
        );

        return $this->noCache(response()->json([
            'success' => true,
            'data' => [
                'stage' => 'challenge',
                'challenge_token' => $token,
            ],
        ], 200));
    }

    /**
     * Stage 2 — ownership verification + disambiguation, then result.
     */
    public function verify(KtaVerifyRequest $request)
    {
        if (! config('kta.enabled', true)) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak tersedia'], 503);
        }

        $check = $this->challenge->validate(
            (string) $request->input('challenge_token'),
            $this->challenge->ipHash($request->ip()),
            $this->challenge->agentHash($request->userAgent()),
        );

        if (! $check['valid']) {
            $status = ($check['reason'] ?? '') === 'challenge_expired' ? 401 : 401;

            return $this->noCache(response()->json([
                'success' => false,
                'message' => 'Sesi verifikasi tidak valid',
            ], $status));
        }

        $payload = $check['payload'];
        $state = $payload['state'];
        $attemptsLeft = (int) ($state['attempts_left'] ?? config('kta.max_attempts', 5));

        if ($attemptsLeft <= 0) {
            return $this->locked();
        }

        $method = (string) $request->input('method');

        // ── Disambiguation path (name+dob resolved to many candidates) ──────
        if ($state['match'] === KtaLookupService::MATCH_MULTIPLE) {
            return $this->handleDisambiguation($request, $state, $attemptsLeft);
        }

        // ── No candidate at all: keep consuming attempts identically ────────
        if ($state['match'] === KtaLookupService::MATCH_NONE) {
            return $this->consumeAttempt($state, $attemptsLeft);
        }

        // ── Single candidate: verify ownership ──────────────────────────────
        $candidateId = (int) ($state['candidate_ids'][0] ?? 0);
        if ($candidateId <= 0) {
            return $this->consumeAttempt($state, $attemptsLeft);
        }

        $answer = $this->normalizeAnswer($method, $request);

        $ok = $this->lookup->verifyOwnership($candidateId, $method, $answer);
        if (! $ok) {
            return $this->consumeAttempt($state, $attemptsLeft);
        }

        return $this->noCache(response()->json([
            'success' => true,
            'data' => $this->lookup->publicResult($candidateId),
        ], 200));
    }

    /**
     * Stepwise disambiguation; never reveals candidates, only narrows.
     */
    protected function handleDisambiguation(KtaVerifyRequest $request, array $state, int $attemptsLeft)
    {
        $method = (string) $request->input('method');
        $order = array_values(config('kta.disambiguate', []));
        $index = (int) ($state['disambiguate_index'] ?? 0);

        if ($method !== 'disambiguate') {
            return $this->consumeAttempt($state, $attemptsLeft);
        }

        $field = (string) $request->input('field');

        // The client must answer the NEXT field in the configured order.
        if (! isset($order[$index]) || $order[$index] !== $field) {
            return $this->consumeAttempt($state, $attemptsLeft);
        }

        $narrowed = $this->lookup->narrowByDisambiguation(
            $state['candidate_ids'] ?? [],
            $field,
            is_array($request->input('value')) ? null : (string) $request->input('value'),
        );

        $nextIndex = $index + 1;

        if (count($narrowed) === 1) {
            $candidateId = (int) $narrowed[0];

            return $this->noCache(response()->json([
                'success' => true,
                'data' => array_merge($this->lookup->publicResult($candidateId), [
                    'verified' => true,
                    'note' => 'Identitas terverifikasi melalui data pendukung.',
                ]),
            ], 200));
        }

        if (count($narrowed) > 1 && isset($order[$nextIndex])) {
            // Still ambiguous: hand back a fresh token with the narrowed pool
            // and the next field to answer. Never discloses the pool size.
            $nextState = array_merge($state, [
                'candidate_ids' => $narrowed,
                'disambiguate_index' => $nextIndex,
                'attempts_left' => $attemptsLeft,
            ]);

            $token = $this->challenge->issue(
                $nextState,
                $this->challenge->ipHash($request->ip()),
                $this->challenge->agentHash($request->userAgent()),
            );

            return $this->noCache(response()->json([
                'success' => true,
                'data' => [
                    'stage' => 'challenge',
                    'challenge_token' => $token,
                ],
            ], 200));
        }

        // No further progress possible → manual review (terminal).
        return $this->noCache(response()->json([
            'success' => true,
            'data' => [
                'stage' => 'manual_review',
                'message' => 'Data belum dapat diverifikasi otomatis. Silakan hubungi admin dengan membawa identitas.',
            ],
        ], 200));
    }

    /**
     * Wrong / insufficient answer: decrement attempts, stay generic.
     */
    protected function consumeAttempt(array $state, int $attemptsLeft)
    {
        $remaining = $attemptsLeft - 1;

        if ($remaining <= 0) {
            return $this->locked();
        }

        $token = $this->challenge->issue(
            array_merge($state, ['attempts_left' => $remaining]),
            $this->challenge->ipHash(request()->ip()),
            $this->challenge->agentHash(request()->userAgent()),
        );

        return $this->noCache(response()->json([
            'success' => false,
            'message' => 'Verifikasi gagal',
            'data' => [
                'stage' => 'challenge',
                'challenge_token' => $token,
                'attempts_left' => $remaining,
            ],
        ], 200));
    }

    /**
     * Attempt budget exhausted.
     */
    protected function locked()
    {
        return $this->noCache(response()->json([
            'success' => false,
            'message' => 'Terlalu banyak percobaan. Silakan mulai ulang.',
            'data' => ['stage' => 'locked'],
        ], 403));
    }

    /**
     * Flatten the per-method answer into a single array for the service.
     *
     * @return array<string, mixed>
     */
    protected function normalizeAnswer(string $method, KtaVerifyRequest $request): array
    {
        $value = $request->input('value');

        if ($method === 'no_hp_fallback' && is_array($value)) {
            return [
                'tahun_masuk' => $value['tahun_masuk'] ?? null,
                'tempat_lahir' => $value['tempat_lahir'] ?? null,
            ];
        }

        return ['value' => is_array($value) ? null : (string) $value];
    }

    /**
     * Add the configured constant response delay (µs) to flatten latency.
     */
    protected function applyResponseDelay(): void
    {
        $micros = (int) config('kta.response_delay_us', 0);
        if ($micros > 0) {
            usleep($micros);
        }
    }

    /**
     * Ensure no intermediary (CDN, browser, proxy) caches a PII-adjacent
     * response.
     */
    protected function noCache($response)
    {
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
