<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Services\KtaLookupService;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Mockery;
use Tests\TestCase;

class PublicKtaStatelessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $lookup = Mockery::mock(KtaLookupService::class);
        $lookup->shouldReceive('resolve')->andReturn([
            'match' => KtaLookupService::MATCH_NONE,
            'candidate_ids' => [],
            'verify_method' => 'hp_last4',
            'disambiguate_index' => 0,
            'attempts_left' => 5,
        ]);
        $this->app->instance(KtaLookupService::class, $lookup);

        config([
            'kta.enabled' => true,
            'kta.print.enabled' => true,
            'kta.response_delay_us' => 0,
            'sanctum.stateful' => ['localhost:8080'],
            'sanctum.middleware.verify_csrf_token' => StrictPublicKtaVerifyCsrfToken::class,
        ]);
    }

    public function testPublicKtaRoutesExcludeStatefulSanctumMiddleware(): void
    {
        foreach ([
            ['POST', '/api/public/kta/check'],
            ['POST', '/api/public/kta/verify'],
            ['GET', '/api/public/kta/print-request'],
            ['POST', '/api/public/kta/print-request'],
        ] as [$method, $uri]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $middleware = app('router')->gatherRouteMiddleware($route);

            $this->assertNotContains(EnsureFrontendRequestsAreStateful::class, $middleware);
        }

        $userRoute = app('router')->getRoutes()->match(Request::create('/api/user', 'GET'));
        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            app('router')->gatherRouteMiddleware($userRoute),
        );
    }

    public function testPublicKtaCheckAndVerifyDoNotRequireCsrfBootstrap(): void
    {
        $check = $this->withHeader('Origin', 'http://localhost:8080')
            ->postJson('/api/public/kta/check', [
                'mode' => 'member_id',
                'id_anggota' => 'MZT-NOT-FOUND',
            ]);

        $check->assertStatus(200)->assertJsonPath('data.stage', 'challenge');

        $this->withHeader('Origin', 'http://localhost:8080')
            ->postJson('/api/public/kta/verify', [
                'challenge_token' => $check->json('data.challenge_token'),
                'method' => 'hp_last4',
                'value' => '0000',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.stage', 'challenge');
    }

    public function testPublicKtaPrintRoutesDoNotRequireCsrfBootstrap(): void
    {
        $this->withHeader('Origin', 'http://localhost:8080')
            ->getJson('/api/public/kta/print-request?print_token=invalid')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Sesi verifikasi tidak valid');

        $this->withHeader('Origin', 'http://localhost:8080')
            ->postJson('/api/public/kta/print-request', [
                'print_token' => 'invalid',
                'delivery_method' => 'pickup',
            ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Sesi verifikasi tidak valid');
    }

    public function testPaymenkuWebhookStillUsesHmacInsteadOfCsrf(): void
    {
        config(['paymenku.webhook_secret' => 'whsec_test_secret']);

        $this->withHeaders([
            'Origin' => 'http://localhost:8080',
            'X-PaymenKu-Timestamp' => (string) time(),
            'X-PaymenKu-Signature' => 'invalid',
        ])->postJson('/api/webhooks/paymenku', [
            'event' => 'payment.status_updated',
        ])->assertStatus(401)->assertJsonPath('error', 'Invalid signature');
    }
}

class StrictPublicKtaVerifyCsrfToken extends VerifyCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
