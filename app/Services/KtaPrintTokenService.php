<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Short-lived, opaque proof that a member completed KTA ownership verification.
 *
 * Issued by the `kta/verify` success path and required by the print-request
 * endpoints. The identity (`user_id`) is carried INSIDE the token, so the
 * frontend never sends `id_users` / `id_anggota` as identity proof.
 *
 * Bound to the requesting IP + User-Agent, with its own (short) TTL and a
 * purpose marker so it cannot be swapped with a lookup challenge token.
 */
class KtaPrintTokenService
{
    public const PURPOSE = 'kta_print_verified';

    public function issue(int $userId, string $ipHash, string $agentHash): string
    {
        $payload = [
            'purpose' => self::PURPOSE,
            'v' => 1,
            'iat' => Carbon::now()->getTimestamp(),
            'exp' => Carbon::now()->addSeconds((int) config('kta.print_token.ttl', 900))->getTimestamp(),
            'ip' => $ipHash,
            'ua' => $agentHash,
            'user_id' => $userId,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{valid: bool, user_id?: int, reason?: string}
     */
    public function validate(?string $token, string $ipHash, string $agentHash): array
    {
        $token = trim((string) $token);
        if ($token === '') {
            return ['valid' => false, 'reason' => 'invalid'];
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException | \JsonException $e) {
            return ['valid' => false, 'reason' => 'invalid'];
        }

        if (! is_array($payload) || ($payload['purpose'] ?? null) !== self::PURPOSE) {
            return ['valid' => false, 'reason' => 'invalid'];
        }
        if (Carbon::now()->getTimestamp() > (int) ($payload['exp'] ?? 0)) {
            return ['valid' => false, 'reason' => 'expired'];
        }
        if (($payload['ip'] ?? null) !== $ipHash) {
            return ['valid' => false, 'reason' => 'invalid'];
        }
        if (($payload['ua'] ?? null) !== $agentHash) {
            return ['valid' => false, 'reason' => 'invalid'];
        }
        if (! isset($payload['user_id']) || (int) $payload['user_id'] <= 0) {
            return ['valid' => false, 'reason' => 'invalid'];
        }

        return ['valid' => true, 'user_id' => (int) $payload['user_id']];
    }
}
