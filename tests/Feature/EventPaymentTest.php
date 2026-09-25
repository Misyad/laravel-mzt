<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPaymentEvent;
use App\Models\HakAksesRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EventPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paymenku.base_url' => 'https://paymenku.test/api/v1',
            'paymenku.api_key' => 'sk_test_event',
            'paymenku.webhook_secret' => 'whsec_event_test',
            'paymenku.webhook_tolerance' => 300,
            'member_onboarding.frontend_url' => 'https://app.example.test',
        ]);

        foreach ([
            'event_payment_events',
            'ticket_logs',
            'tickets',
            'payment_logs',
            'payment_proofs',
            'payments',
            'orders',
            'events',
            'hak_akses_role',
            'personal_access_tokens',
            'data_users',
            'users',
        ] as $table) {
            if (\Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        $this->seedActiveRoleCatalog(['anggota', 'admin']);
    }

    private function makeUser(string $idAnggota): User
    {
        $user = User::factory()->create([
            'id_anggota' => $idAnggota,
            'is_active' => '1',
            'password_changed_at' => now(),
        ]);
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => 'anggota',
            'hak_akses' => 'access',
        ]);

        return $user;
    }

    private function makeEvent(int $amount = 100000): Event
    {
        return Event::create([
            'judul_event' => 'Event '.uniqid(),
            'slug' => 'event-'.uniqid(),
            'lokasi' => 'Lokasi',
            'harga' => (string) $amount,
            'harga_amount' => $amount,
            'deskripsi' => 'Deskripsi',
            'tanggal' => '01/01/2030 - 02/01/2030',
            'tanggal_mulai' => '2030-01-01',
            'tanggal_selesai' => '2030-01-02',
            'is_active' => '1',
            'kuota' => 100,
            'visibility' => 'public',
        ]);
    }

    private function fakeGateway(string $transactionId = 'EVENT-TRX-1', string $amount = '101500.00'): void
    {
        Http::fake([
            'paymenku.test/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'trx_id' => $transactionId,
                    'amount' => $amount,
                    'status' => 'pending',
                    'pay_url' => 'https://paymenku.test/pay/'.$transactionId,
                    'expires_at' => '2030-01-01T10:00:00+00:00',
                ],
            ], 200),
        ]);
    }

    private function registerPaidEvent(User $user, string $transactionId = 'EVENT-TRX-1'): array
    {
        $event = $this->makeEvent();
        Sanctum::actingAs($user);
        $this->fakeGateway($transactionId);

        $response = $this->postJson("/api/events/{$event->id}/register", [
            'payment_choice' => 'pay_now',
        ])->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $payment = Payment::where('id_order', $order->id)->firstOrFail();

        return [$order, $payment, $response];
    }

    private function signedWebhook(array $payload): array
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_event_test');

        return [$raw, $timestamp, $signature];
    }

    private function sendWebhook(array $payload)
    {
        [$raw, $timestamp, $signature] = $this->signedWebhook($payload);

        return $this->call('POST', '/api/webhooks/paymenku', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $timestamp,
            'HTTP_X_PAYMENKU_SIGNATURE' => $signature,
        ], $raw);
    }

    private function payload(Payment $payment, string $status = 'paid', ?string $amount = null): array
    {
        return [
            'event' => 'payment.status_updated',
            'trx_id' => $payment->transaction_id,
            'reference_id' => $payment->reference,
            'status' => $status,
            'amount' => $amount ?? (string) $payment->gateway_total,
        ];
    }

    public function test_pay_now_registration_creates_gateway_checkout_with_stable_reference(): void
    {
        $user = $this->makeUser('EVENT001');
        [$order, $payment, $response] = $this->registerPaidEvent($user);

        $reference = 'EVENT-'.$order->uuid;
        $response
            ->assertJsonPath('data.payment.reference', $reference)
            ->assertJsonPath('data.payment.transaction_id', 'EVENT-TRX-1')
            ->assertJsonPath('data.payment.base_amount', '100000.00')
            ->assertJsonPath('data.payment.gateway_fee', '1500.00')
            ->assertJsonPath('data.payment.gateway_total', '101500.00');

        $this->assertSame('100000.00', $payment->amount);
        $this->assertSame('paymenku', $payment->provider);
        $this->assertNotNull($payment->expires_at);
        $this->assertDatabaseHas('payment_logs', [
            'id_payment' => $payment->id,
            'old_status' => null,
            'new_status' => 'pending',
        ]);
        Http::assertSent(function (HttpRequest $request) use ($reference) {
            return $request->hasHeader('Idempotency-Key', $reference)
                && $request['reference_id'] === $reference
                && $request['amount'] === 100000;
        });
    }

    public function test_checkout_retry_returns_same_payment_without_second_gateway_request(): void
    {
        $user = $this->makeUser('EVENT002');
        [$order, $payment] = $this->registerPaidEvent($user);

        $this->postJson("/api/orders/{$order->uuid}/checkout")
            ->assertStatus(200)
            ->assertJsonPath('data.payment.id', $payment->id);

        $this->assertSame(1, Payment::where('id_order', $order->id)->where('provider', 'paymenku')->count());
        Http::assertSentCount(1);
    }

    public function test_checkout_enforces_owner_payment_choice_and_unpaid_order(): void
    {
        $owner = $this->makeUser('EVENT003');
        [$order] = $this->registerPaidEvent($owner);
        $other = $this->makeUser('EVENT004');

        Sanctum::actingAs($other);
        $this->postJson("/api/orders/{$order->uuid}/checkout")->assertStatus(403);

        HakAksesRole::create([
            'id_users' => $other->id,
            'nama_role' => 'admin',
            'hak_akses' => 'access',
        ]);
        $this->postJson("/api/orders/{$order->uuid}/checkout")->assertStatus(403);

        $venueEvent = $this->makeEvent();
        Sanctum::actingAs($owner);
        $venueResponse = $this->postJson("/api/events/{$venueEvent->id}/register", [
            'payment_choice' => 'pay_at_venue',
        ])->assertStatus(201);
        $this->postJson('/api/orders/'.$venueResponse->json('data.uuid').'/checkout')->assertStatus(422);

        $order->forceFill(['payment_status' => 'paid'])->save();
        $this->postJson("/api/orders/{$order->uuid}/checkout")->assertStatus(409);
    }

    public function test_webhook_rejects_reference_transaction_and_gateway_total_mismatch(): void
    {
        $user = $this->makeUser('EVENT005');
        [$order, $payment] = $this->registerPaidEvent($user);
        $payloads = [
            array_merge($this->payload($payment), ['reference_id' => 'EVENT-wrong']),
            array_merge($this->payload($payment), ['trx_id' => 'WRONG-TRX']),
            $this->payload($payment, 'paid', '101500.01'),
        ];

        foreach ($payloads as $payload) {
            $this->sendWebhook($payload)->assertStatus(422);
            $this->assertSame('pending', $payment->fresh()->status);
            $this->assertSame('pending', $order->fresh()->payment_status);
            $this->assertSame(0, $order->tickets()->count());
        }

        $this->assertSame(3, EventPaymentEvent::where('outcome', 'mismatch')->count());
    }

    public function test_paid_webhook_atomically_marks_paid_and_issues_one_ticket_with_logs(): void
    {
        $user = $this->makeUser('EVENT006');
        [$order, $payment] = $this->registerPaidEvent($user);

        $this->sendWebhook($this->payload($payment))->assertStatus(200);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, $order->tickets()->count());
        $this->assertDatabaseHas('payment_logs', [
            'id_payment' => $payment->id,
            'old_status' => 'pending',
            'new_status' => 'paid',
        ]);
        $ticket = $order->tickets()->firstOrFail();
        $this->assertDatabaseHas('ticket_logs', [
            'id_ticket' => $ticket->id,
            'old_status' => 'draft',
            'new_status' => 'issued',
        ]);
    }

    public function test_duplicate_paid_webhook_is_idempotent(): void
    {
        $user = $this->makeUser('EVENT007');
        [$order, $payment] = $this->registerPaidEvent($user);
        $payload = $this->payload($payment);

        $this->sendWebhook($payload)->assertStatus(200);
        $this->sendWebhook($payload)->assertStatus(200);

        $this->assertSame(1, $order->tickets()->count());
        $this->assertSame(1, EventPaymentEvent::where('payment_id', $payment->id)->count());
        $this->assertSame(1, $payment->logs()->where('new_status', 'paid')->count());
    }

    public function test_concurrent_duplicate_webhook_issues_one_ticket(): void
    {
        $user = $this->makeUser('EVENT008');
        [$order, $payment] = $this->registerPaidEvent($user);
        $payload = $this->payload($payment);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $raw);
        $gate = tempnam(sys_get_temp_dir(), 'event-webhook-');
        unlink($gate);

        $script = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[3])) {
    usleep(1000);
}
$payload = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$result = app(App\Services\EventPaymentService::class)->applyPaymentEvent($argv[2], $payload);
exit(($result['ok'] ?? false) ? 0 : 1);
PHP;

        $command = [PHP_BINARY, '-r', $script, base64_encode($raw), $hash, $gate];
        $first = new Process($command, base_path());
        $second = new Process($command, base_path());
        $first->start();
        $second->start();
        touch($gate);

        try {
            $first->wait();
            $second->wait();
        } finally {
            @unlink($gate);
        }

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $this->assertSame(1, EventPaymentEvent::where('payload_hash', $hash)->count());
        $this->assertSame(1, $order->tickets()->count());
        $this->assertSame(1, $payment->logs()->where('new_status', 'paid')->count());
    }

    private function assertTerminalWebhookDoesNotIssueTicket(string $status, string $memberId, string $transactionId): void
    {
        $user = $this->makeUser($memberId);
        [$order, $payment] = $this->registerPaidEvent($user, $transactionId);
        $payload = $this->payload($payment, $status);

        $this->sendWebhook($payload)->assertStatus(200);
        $this->sendWebhook($payload)->assertStatus(200);

        $this->assertSame($status, $payment->fresh()->status);
        $this->assertSame($status, $order->fresh()->payment_status);
        $this->getJson("/api/orders/{$order->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', $status);
        $this->assertSame(0, $order->tickets()->count());
        $this->assertSame(1, EventPaymentEvent::where('payment_id', $payment->id)->where('status', $status)->count());
        $this->assertSame(1, $payment->logs()->where('new_status', $status)->count());
    }

    public function test_expired_webhook_does_not_issue_ticket(): void
    {
        $this->assertTerminalWebhookDoesNotIssueTicket('expired', 'TERMINAL1', 'TERMINAL-TRX-1');
    }

    public function test_cancelled_webhook_does_not_issue_ticket(): void
    {
        $this->assertTerminalWebhookDoesNotIssueTicket('cancelled', 'TERMINAL2', 'TERMINAL-TRX-2');
    }

    public function test_failed_webhook_does_not_issue_ticket(): void
    {
        $this->assertTerminalWebhookDoesNotIssueTicket('failed', 'TERMINAL3', 'TERMINAL-TRX-3');
    }

    public function test_terminal_and_late_paid_events_are_audited_without_ticket(): void
    {
        $user = $this->makeUser('EVENT009');
        [$order, $payment] = $this->registerPaidEvent($user);

        $this->sendWebhook($this->payload($payment, 'expired'))->assertStatus(200);
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame(0, $order->tickets()->count());

        $this->sendWebhook($this->payload($payment, 'paid'))->assertStatus(200);
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame(0, $order->tickets()->count());
        $this->assertDatabaseHas('event_payment_events', [
            'payment_id' => $payment->id,
            'status' => 'paid',
            'outcome' => 'late_paid',
        ]);
        $this->assertDatabaseHas('payment_logs', [
            'id_payment' => $payment->id,
            'old_status' => 'expired',
            'new_status' => 'expired',
            'note' => 'late_paid_after_terminal',
        ]);
    }
}
