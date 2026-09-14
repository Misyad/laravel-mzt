<?php

namespace Tests\Unit;

use App\Services\KtaChallengeService;
use Tests\TestCase;

/**
 * Challenge token: signed/opaque, TTL, IP/UA binding, tamper resistance.
 * Extends the Laravel TestCase because Crypt needs the app encrypter.
 */
class KtaChallengeServiceTest extends TestCase
{
    protected function service(): KtaChallengeService
    {
        return new KtaChallengeService();
    }

    public function testIssueThenValidateRoundTrip(): void
    {
        $s = $this->service();
        $ipHash = $s->ipHash('10.0.0.1');
        $uaHash = $s->agentHash('phpunit');

        $token = $s->issue([
            'candidate_ids' => [42],
            'match' => 'single',
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
            'mode' => 'name_dob',
        ], $ipHash, $uaHash);

        $result = $s->validate($token, $ipHash, $uaHash);

        $this->assertTrue($result['valid']);
        $this->assertSame([42], $result['payload']['state']['candidate_ids']);
        $this->assertSame('single', $result['payload']['state']['match']);
    }

    public function testTokenIsOpaqueAndNotPlaintextJson(): void
    {
        $s = $this->service();
        $token = $s->issue([
            'candidate_ids' => [42],
            'match' => 'single',
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
            'mode' => 'name_dob',
        ], $s->ipHash('10.0.0.1'), $s->agentHash('ua'));

        $this->assertStringNotContainsString('candidate_ids', $token);
        $this->assertStringNotContainsString('42', $token);
        $this->assertStringNotContainsString('{', $token);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $s = $this->service();
        $ipHash = $s->ipHash('10.0.0.1');
        $uaHash = $s->agentHash('ua');

        $token = $s->issue([
            'candidate_ids' => [1],
            'match' => 'single',
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
            'mode' => 'name_dob',
        ], $ipHash, $uaHash);

        $tampered = substr($token, 0, -4) . 'AAAA';
        $this->assertFalse($s->validate($tampered, $ipHash, $uaHash)['valid']);
    }

    public function testTokenBoundToIp(): void
    {
        $s = $this->service();
        $token = $s->issue([
            'candidate_ids' => [1],
            'match' => 'single',
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
            'mode' => 'name_dob',
        ], $s->ipHash('10.0.0.1'), $s->agentHash('ua'));

        $this->assertFalse($s->validate($token, $s->ipHash('10.0.0.2'), $s->agentHash('ua'))['valid']);
    }

    public function testTokenBoundToUserAgent(): void
    {
        $s = $this->service();
        $token = $s->issue([
            'candidate_ids' => [1],
            'match' => 'single',
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
            'mode' => 'name_dob',
        ], $s->ipHash('10.0.0.1'), $s->agentHash('ua-a'));

        $this->assertFalse($s->validate($token, $s->ipHash('10.0.0.1'), $s->agentHash('ua-b'))['valid']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $s = $this->service();
        $ipHash = $s->ipHash('10.0.0.1');
        $uaHash = $s->agentHash('ua');

        config(['kta.token.ttl' => -1]);
        $token = $s->issue([
            'candidate_ids' => [1],
            'match' => 'single',
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
            'mode' => 'name_dob',
        ], $ipHash, $uaHash);

        $result = $s->validate($token, $ipHash, $uaHash);
        $this->assertFalse($result['valid']);
        $this->assertSame('challenge_expired', $result['reason']);
    }

    public function testGarbageTokenIsRejected(): void
    {
        $s = $this->service();
        $this->assertFalse($s->validate('not-a-token', $s->ipHash('1.1.1.1'), $s->agentHash('ua'))['valid']);
        $this->assertFalse($s->validate('', $s->ipHash('1.1.1.1'), $s->agentHash('ua'))['valid']);
    }

    public function testIpHashDoesNotStoreRawIp(): void
    {
        $s = $this->service();
        $this->assertStringNotContainsString('10.0.0.1', $s->ipHash('10.0.0.1'));
    }
}
