<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Issues and validates the opaque challenge token used by the public
 * "Cek Status KTA" flow.
 *
 * The token is the ONLY place the real lookup state lives. The public
 * response is intentionally generic (`stage: challenge`), so the client can
 * never tell whether a lookup produced zero, one, or many candidates.
 *
 * Security properties:
 *  - opaque to the client (Laravel Encrypter, AES-256-CBC + HMAC)
 *  - bound to the requesting IP and User-Agent hashes
 *  - hard TTL (config/kta.php)
 *  - bounded verification attempts stored inside the token itself
 *  - candidate id and disambiguation progress are server-side only
 */
class KtaChallengeService
{
    /**
     * Create a fresh challenge token for a resolved lookup.
     *
     * @param  array{
     *     candidate_ids: array<int, int>,
     *     match: string,
     *     verify_method: string,
     *     disambiguate_index: int,
     *     attempts_left: int,
     *     mode: string
     * }  $state
     */
    public function issue(array $state, string $ipHash, string $agentHash): string
    {
        $payload = [
            'v' => 1,
            'iat' => Carbon::now()->getTimestamp(),
            'exp' => Carbon::now()->addSeconds((int) config('kta.token.ttl', 600))->getTimestamp(),
            'ip' => config('kta.token.bind_ip', true) ? $ipHash : null,
            'ua' => config('kta.token.bind_agent', true) ? $agentHash : null,
            'state' => $state,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decode and validate a challenge token.
     *
     * @return array{valid: bool, reason?: string, payload?: array}
     */
    public function validate(?string $token, string $ipHash, string $agentHash): array
    {
        $token = trim((string) $token);
        if ($token === '') {
            return ['valid' => false, 'reason' => 'challenge_invalid'];
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException | \JsonException $e) {
            return ['valid' => false, 'reason' => 'challenge_invalid'];
        }

        if (! is_array($payload) || ! isset($payload['exp'], $payload['state'])) {
            return ['valid' => false, 'reason' => 'challenge_invalid'];
        }

        if (Carbon::now()->getTimestamp() > (int) $payload['exp']) {
            return ['valid' => false, 'reason' => 'challenge_expired'];
        }

        if (config('kta.token.bind_ip', true) && ($payload['ip'] ?? null) !== $ipHash) {
            return ['valid' => false, 'reason' => 'challenge_invalid'];
        }

        if (config('kta.token.bind_agent', true) && ($payload['ua'] ?? null) !== $agentHash) {
            return ['valid' => false, 'reason' => 'challenge_invalid'];
        }

        return ['valid' => true, 'payload' => $payload];
    }

    /**
     * Stable, deterministic hash of the client IP for token binding. Must be
     * reproducible across requests, so a keyed HMAC is used instead of a
     * salted password hash (which would differ on every call). The raw IP is
     * never stored.
     */
    public function ipHash(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }

    /**
     * Normalised, truncated User-Agent hash for token binding.
     */
    public function agentHash(?string $agent): string
    {
        return hash('sha256', mb_substr((string) $agent, 0, 255));
    }
}
