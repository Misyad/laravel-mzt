<?php

namespace Tests\Feature;

use App\Enums\KtaPrintStatus;
use App\Models\DataUser;
use App\Models\HakAksesRole;
use App\Models\KtaPaymentEvent;
use App\Models\KtaPrintRequest;
use App\Models\KtaPrintRequestLog;
use App\Models\User;
use App\Services\KtaChallengeService;
use App\Services\KtaPrintRequestService;
use App\Services\KtaPrintTokenService;
use App\Services\KtaLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Physical KTA print request workflow (PRD v3.0).
 *
 * Schema is built manually (DashboardTest pattern) because the legacy prod
 * migration chain depends on a prod-only `events.harga` column.
 */
class KtaPrintRequestTest extends TestCase
{
    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }
        // Another test class may have dropped/recreated shared tables; repair
        // the ones this suite depends on so it is order-independent.
        $this->repairSharedSchema();

        $this->truncate([
            'kta_payment_events',
            'kta_print_request_logs',
            'kta_print_requests',
            'data_users',
            'hak_akses_role',
            'sessions',
            'users',
        ]);

        config([
            'kta.enabled' => true,
            'kta.print.enabled' => true,
            'kta.print.amount' => 25000,
            'kta.print.channel_code' => 'qris',
            'kta.response_delay_us' => 0,
            'paymenku.api_key' => 'sk_test_dummy',
            'paymenku.webhook_secret' => 'whsec_test_secret',
            'paymenku.base_url' => 'https://paymenku.test/api/v1',
            'paymenku.webhook_tolerance' => 0,
        ]);
    }

    private function buildSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($t) {
                $t->id();
                $t->string('name');
                $t->string('email')->nullable();
                $t->string('password')->nullable();
                $t->string('id_anggota')->nullable()->index();
                $t->string('is_active')->default('1');
                $t->timestamps();
            });
        } else {
            foreach (['id_anggota' => fn ($t) => $t->string('id_anggota')->nullable(), 'is_active' => fn ($t) => $t->string('is_active')->default('1')] as $col => $cb) {
                if (! Schema::hasColumn('users', $col)) {
                    Schema::table('users', fn ($table) => $cb($table));
                }
            }
        }
        if (! Schema::hasTable('data_users')) {
            Schema::create('data_users', function ($t) {
                $t->id();
                $t->integer('id_users');
                $t->string('no_hp')->nullable();
                $t->text('barcode')->nullable();
                $t->text('alamat')->nullable();
                $t->string('pekerjaan')->nullable();
                $t->string('niqobah')->nullable();
                $t->date('tanggal_lahir')->nullable();
                $t->date('tahun_masuk')->nullable();
                $t->date('tahun_keluar')->nullable();
                $t->string('tempat_lahir')->nullable();
                $t->text('foto')->nullable();
                $t->string('is_active')->default('1');
                $t->timestamps();
            });
        } else {
            // Another test class may have dropped/recreated data_users without
            // all columns; repair defensively so this suite is order-independent.
            foreach ([
                'no_hp' => fn ($t) => $t->string('no_hp')->nullable(),
                'alamat' => fn ($t) => $t->text('alamat')->nullable(),
                'pekerjaan' => fn ($t) => $t->string('pekerjaan')->nullable(),
                'niqobah' => fn ($t) => $t->string('niqobah')->nullable(),
                'tanggal_lahir' => fn ($t) => $t->date('tanggal_lahir')->nullable(),
                'tahun_masuk' => fn ($t) => $t->date('tahun_masuk')->nullable(),
                'tahun_keluar' => fn ($t) => $t->date('tahun_keluar')->nullable(),
                'tempat_lahir' => fn ($t) => $t->string('tempat_lahir')->nullable(),
                'foto' => fn ($t) => $t->text('foto')->nullable(),
                'barcode' => fn ($t) => $t->text('barcode')->nullable(),
                'is_active' => fn ($t) => $t->string('is_active')->default('1'),
            ] as $col => $cb) {
                if (! Schema::hasColumn('data_users', $col)) {
                    Schema::table('data_users', fn ($table) => $cb($table));
                }
            }
        }
        if (! Schema::hasTable('hak_akses_role')) {
            Schema::create('hak_akses_role', function ($t) {
                $t->id();
                $t->integer('id_users');
                $t->string('nama_role');
                $t->enum('hak_akses', ['access', 'no_accesss'])->default('access');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function ($t) {
                $t->string('id')->primary();
                $t->foreignId('user_id')->nullable()->index();
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->longText('payload');
                $t->integer('last_activity')->index();
            });
        }
        if (! Schema::hasTable('kta_print_requests')) {
            Schema::create('kta_print_requests', function ($t) {
                $t->id();
                $t->unsignedBigInteger('id_users');
                $t->string('id_anggota_snapshot', 50);
                $t->string('status', 30)->default('menunggu_pembayaran');
                $t->string('delivery_method', 20)->default('pickup');
                $t->string('payment_provider', 30)->nullable();
                $t->string('payment_reference', 60)->nullable();
                $t->string('payment_trx_id')->nullable();
                $t->decimal('payment_amount', 12, 2)->nullable();
                $t->string('payment_status', 20)->default('pending');
                $t->dateTime('paid_at')->nullable();
                $t->string('payment_channel', 30)->nullable();
                $t->text('pay_url')->nullable();
                $t->string('recipient_name')->nullable();
                $t->string('recipient_phone', 30)->nullable();
                $t->text('shipping_address')->nullable();
                $t->dateTime('submitted_at')->nullable();
                $t->dateTime('printed_at')->nullable();
                $t->dateTime('ready_at')->nullable();
                $t->dateTime('shipped_at')->nullable();
                $t->dateTime('completed_at')->nullable();
                $t->dateTime('rejected_at')->nullable();
                $t->text('rejection_reason')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('printed_by')->nullable();
                $t->unsignedBigInteger('completed_by')->nullable();
                $t->unsignedBigInteger('active_key')->nullable()->unique();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('kta_print_request_logs')) {
            Schema::create('kta_print_request_logs', function ($t) {
                $t->id();
                $t->unsignedBigInteger('kta_print_request_id');
                $t->string('old_status', 30)->nullable();
                $t->string('new_status', 30);
                $t->text('reason')->nullable();
                $t->string('source', 40)->default('admin');
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('kta_payment_events')) {
            Schema::create('kta_payment_events', function ($t) {
                $t->id();
                $t->unsignedBigInteger('kta_print_request_id')->nullable();
                $t->string('provider', 30)->default('paymenku');
                $t->string('event_type', 60)->nullable();
                $t->string('trx_id')->nullable();
                $t->string('reference_id', 60)->nullable();
                $t->string('status', 30)->nullable();
                $t->string('payload_hash', 64)->nullable();
                $t->boolean('signature_valid')->default(false);
                $t->dateTime('processed_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Ensure shared tables still have the columns this suite needs, even when
     * a previously-run test class recreated them differently.
     */
    private function repairSharedSchema(): void
    {
        if (Schema::hasTable('users')) {
            foreach (['id_anggota' => fn ($t) => $t->string('id_anggota')->nullable(), 'is_active' => fn ($t) => $t->string('is_active')->default('1')] as $col => $cb) {
                if (! Schema::hasColumn('users', $col)) {
                    Schema::table('users', fn ($table) => $cb($table));
                }
            }
        }
        if (Schema::hasTable('data_users')) {
            foreach ([
                'no_hp' => fn ($t) => $t->string('no_hp')->nullable(),
                'tanggal_lahir' => fn ($t) => $t->date('tanggal_lahir')->nullable(),
                'tahun_masuk' => fn ($t) => $t->date('tahun_masuk')->nullable(),
                'tahun_keluar' => fn ($t) => $t->date('tahun_keluar')->nullable(),
                'tempat_lahir' => fn ($t) => $t->string('tempat_lahir')->nullable(),
                'niqobah' => fn ($t) => $t->string('niqobah')->nullable(),
            ] as $col => $cb) {
                if (! Schema::hasColumn('data_users', $col)) {
                    Schema::table('data_users', fn ($table) => $cb($table));
                }
            }
        }
    }

    private function truncate(array $tables): void
    {        foreach ($tables as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->delete();
            }
        }
    }

    private function makeMember(array $o = []): User
    {
        $u = new User();
        $u->name = $o['name'] ?? 'Achmad Hasanudin';
        $u->email = $o['email'] ?? 'a@example.test';
        $u->password = bcrypt('secret');
        $u->id_anggota = $o['id_anggota'] ?? '0174011119';
        $u->is_active = $o['is_active'] ?? '1';
        $u->save();

        DataUser::create([
            'id_users' => $u->id,
            'no_hp' => $o['no_hp'] ?? '08883882559',
            'alamat' => 'Jl. Contoh',
            'pekerjaan' => 'Freelancer',
            'niqobah' => 'Pakis',
            'tanggal_lahir' => '2001-07-13',
            'tahun_masuk' => '2011-07-12',
            'tahun_keluar' => '2019-11-11',
            'tempat_lahir' => 'Malang',
            'foto' => '',
            'barcode' => '',
            'is_active' => '1',
        ]);

        return $u;
    }

    private function makeStaff(string $role): User
    {
        $u = User::factory()->create(['is_active' => '1']);
        HakAksesRole::create(['id_users' => $u->id, 'nama_role' => $role, 'hak_akses' => 'access']);
        return $u;
    }

    /** Issue a valid print token bound to the test request context. */
    private function printToken(int $userId): string
    {
        $challenge = new KtaChallengeService();
        return (new KtaPrintTokenService())->issue(
            $userId,
            $challenge->ipHash('127.0.0.1'),
            $challenge->agentHash('Symfony'),
        );
    }

    // ─────────────────────────── creation ───────────────────────────────────

    public function test_requires_valid_print_token(): void
    {
        $this->makeMember();
        $this->postJson('/api/public/kta/print-request', ['delivery_method' => 'pickup'])
            ->assertStatus(401);
    }

    public function test_rejects_token_without_verified_purpose(): void
    {
        $u = $this->makeMember();
        // A lookup challenge token must NOT be accepted as a print token.
        $challenge = new KtaChallengeService();
        $bad = $challenge->issue([
            'candidate_ids' => [$u->id], 'match' => 'single', 'verify_method' => 'hp_last4',
            'disambiguate_index' => 0, 'attempts_left' => 5, 'mode' => 'name_dob',
        ], $challenge->ipHash('127.0.0.1'), $challenge->agentHash('Symfony'));

        $this->postJson('/api/public/kta/print-request', [
            'print_token' => $bad, 'delivery_method' => 'pickup',
        ])->assertStatus(401);
    }

    public function test_creates_request_and_paymenku_transaction(): void
    {
        $u = $this->makeMember();

        Http::fake([
            'paymenku.test/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'trx_id' => 'IDP-TEST-1',
                    'reference_id' => 'KTA-1',
                    'amount' => '25750.00',
                    'status' => 'pending',
                    'pay_url' => 'https://paymenku.com/pay/IDP-TEST-1',
                ],
            ], 200),
        ]);

        $res = $this->postJson('/api/public/kta/print-request', [
            'print_token' => $this->printToken($u->id),
            'delivery_method' => 'pickup',
        ]);

        $res->assertStatus(201)
            ->assertJson(['success' => true, 'data' => ['request' => [
                'status' => 'menunggu_pembayaran',
                'payment_status' => 'pending',
                'delivery_method' => 'pickup',
            ]]]);

        $this->assertDatabaseHas('kta_print_requests', [
            'id_users' => $u->id,
            'status' => 'menunggu_pembayaran',
            'payment_trx_id' => 'IDP-TEST-1',
        ]);
    }

    public function test_inactive_member_cannot_create(): void
    {
        $u = $this->makeMember(['is_active' => '0']);

        $this->postJson('/api/public/kta/print-request', [
            'print_token' => $this->printToken($u->id),
            'delivery_method' => 'pickup',
        ])->assertStatus(403);

        $this->assertSame(0, KtaPrintRequest::count());
    }

    public function test_delivery_requires_address(): void
    {
        $u = $this->makeMember();

        $this->postJson('/api/public/kta/print-request', [
            'print_token' => $this->printToken($u->id),
            'delivery_method' => 'delivery',
        ])->assertStatus(422);
    }

    public function test_duplicate_submit_is_idempotent(): void
    {
        $u = $this->makeMember();
        Http::fake(['paymenku.test/*' => Http::response([
            'status' => 'success',
            'data' => ['trx_id' => 'IDP-DUP', 'reference_id' => 'KTA-1', 'amount' => '25750', 'status' => 'pending', 'pay_url' => 'u'],
        ], 200)]);

        $token = $this->printToken($u->id);

        $first = $this->postJson('/api/public/kta/print-request', ['print_token' => $token, 'delivery_method' => 'pickup']);
        $second = $this->postJson('/api/public/kta/print-request', ['print_token' => $token, 'delivery_method' => 'pickup']);

        $first->assertStatus(201);
        $second->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame(1, KtaPrintRequest::count());
        $this->assertSame(0, KtaPaymentEvent::count()); // no payment events on create
    }

    public function test_concurrent_create_collides_safely(): void
    {
        $u = $this->makeMember();
        $service = app(KtaPrintRequestService::class);

        $a = $service->createForUser($u, ['delivery_method' => 'pickup']);
        $b = $service->createForUser($u, ['delivery_method' => 'pickup']);

        $this->assertTrue($a['ok']);
        $this->assertTrue($b['ok']);
        $this->assertSame(1, KtaPrintRequest::count());
        $this->assertSame($a['request']->id, $b['request']->id);
    }

    // ─────────────────────────── webhook / payment ──────────────────────────

    private function signedWebhook(array $payload): array
    {
        $raw = json_encode($payload);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $raw, 'whsec_test_secret');
        return [$raw, $ts, $sig];
    }

    private function paidPayload(string $ref, string $trx, string $amount = '25750.00'): array
    {
        return [
            'event' => 'payment.status_updated',
            'trx_id' => $trx,
            'reference_id' => $ref,
            'status' => 'paid',
            'amount' => $amount,
        ];
    }

    private function makePending(int $userId, string $ref = 'KTA-1', string $trx = 'IDP-1'): KtaPrintRequest
    {
        return KtaPrintRequest::create([
            'id_users' => $userId,
            'id_anggota_snapshot' => '0174011119',
            'status' => KtaPrintStatus::MENUNGGU_PEMBAYARAN->value,
            'delivery_method' => 'pickup',
            'payment_provider' => 'paymenku',
            'payment_reference' => $ref,
            'payment_trx_id' => $trx,
            'payment_amount' => 25000,
            'payment_status' => 'pending',
            'submitted_at' => now(),
            'active_key' => $userId,
        ]);
    }

    public function test_webhook_invalid_signature_rejected(): void
    {
        $u = $this->makeMember();
        $this->makePending($u->id);

        [$raw, $ts] = $this->signedWebhook($this->paidPayload('KTA-1', 'IDP-1'));

        $this->call('POST', '/api/webhooks/paymenku', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $ts,
            'HTTP_X_PAYMENKU_SIGNATURE' => 'deadbeef',
        ], $raw)->assertStatus(401);

        $this->assertSame('menunggu_pembayaran', KtaPrintRequest::first()->status);
    }

    public function test_webhook_paid_transitions_to_menunggu_cetak(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);

        [$raw, $ts, $sig] = $this->signedWebhook($this->paidPayload('KTA-1', 'IDP-1'));

        $this->call('POST', '/api/webhooks/paymenku', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $ts,
            'HTTP_X_PAYMENKU_SIGNATURE' => $sig,
        ], $raw)->assertStatus(200);

        $req->refresh();
        $this->assertSame('paid', $req->payment_status);
        $this->assertSame('menunggu_cetak', $req->status);
        $this->assertNotNull($req->paid_at);
        $this->assertDatabaseHas('kta_print_request_logs', [
            'kta_print_request_id' => $req->id,
            'new_status' => 'menunggu_cetak',
            'source' => 'paymenku_webhook',
        ]);
    }

    public function test_webhook_duplicate_is_idempotent(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);

        [$raw, $ts, $sig] = $this->signedWebhook($this->paidPayload('KTA-1', 'IDP-1'));
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $ts,
            'HTTP_X_PAYMENKU_SIGNATURE' => $sig,
        ];

        $this->call('POST', '/api/webhooks/paymenku', [], [], [], $headers, $raw)->assertStatus(200);
        $this->call('POST', '/api/webhooks/paymenku', [], [], [], $headers, $raw)->assertStatus(200);

        $req->refresh();
        $this->assertSame('menunggu_cetak', $req->status);
        // Exactly one transition log for the paid event.
        $this->assertSame(
            1,
            KtaPrintRequestLog::where('kta_print_request_id', $req->id)->where('new_status', 'menunggu_cetak')->count()
        );
    }

    public function test_webhook_wrong_amount_does_not_advance(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);

        [$raw, $ts, $sig] = $this->signedWebhook($this->paidPayload('KTA-1', 'IDP-1', '100.00'));

        $this->call('POST', '/api/webhooks/paymenku', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $ts,
            'HTTP_X_PAYMENKU_SIGNATURE' => $sig,
        ], $raw)->assertStatus(422);

        $this->assertSame('menunggu_pembayaran', $req->refresh()->status);
    }

    public function test_webhook_unknown_reference_acknowledged(): void
    {
        [$raw, $ts, $sig] = $this->signedWebhook($this->paidPayload('KTA-999', 'IDP-999'));

        $this->call('POST', '/api/webhooks/paymenku', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $ts,
            'HTTP_X_PAYMENKU_SIGNATURE' => $sig,
        ], $raw)->assertStatus(200);
    }

    public function test_webhook_expired_marks_expired_and_frees_slot(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);

        $payload = array_merge($this->paidPayload('KTA-1', 'IDP-1'), ['status' => 'expired']);
        [$raw, $ts, $sig] = $this->signedWebhook($payload);

        $this->call('POST', '/api/webhooks/paymenku', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYMENKU_TIMESTAMP' => $ts,
            'HTTP_X_PAYMENKU_SIGNATURE' => $sig,
        ], $raw)->assertStatus(200);

        $req->refresh();
        $this->assertSame('pembayaran_expired', $req->status);
        $this->assertNull($req->active_key);
    }

    // ─────────────────────────── admin transitions ──────────────────────────

    public function test_admin_queue_requires_role(): void
    {
        $this->getJson('/api/kta/print-requests')->assertStatus(401);

        Sanctum::actingAs($this->makeStaff('anggota'));
        // 'anggota' is not staff → forbidden by policy
        $this->getJson('/api/kta/print-requests')->assertStatus(403);
    }

    public function test_admin_queue_defaults_to_production_statuses_only(): void
    {
        $u = $this->makeMember();
        $this->makePending($u->id); // menunggu_pembayaran → excluded by default

        Sanctum::actingAs($this->makeStaff('finance'));
        $res = $this->getJson('/api/kta/print-requests')->assertStatus(200)->json('data');

        $this->assertSame(0, $res['total']);
    }

    public function test_admin_can_run_full_pickup_flow(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);
        $req->forceFill(['status' => 'menunggu_cetak', 'payment_status' => 'paid'])->save();

        Sanctum::actingAs($this->makeStaff('finance'));

        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'sudah_dicetak'])->assertStatus(200);
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'siap_diambil'])->assertStatus(200);
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'selesai'])->assertStatus(200);

        $req->refresh();
        $this->assertSame('selesai', $req->status);
        $this->assertNull($req->active_key);
    }

    public function test_admin_can_run_full_delivery_flow(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);
        $req->forceFill(['status' => 'menunggu_cetak', 'payment_status' => 'paid', 'delivery_method' => 'delivery'])->save();

        Sanctum::actingAs($this->makeStaff('ketua'));

        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'sudah_dicetak'])->assertStatus(200);
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'dikirim'])->assertStatus(200);
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'selesai'])->assertStatus(200);

        $this->assertSame('selesai', $req->refresh()->status);
    }

    public function test_illegal_transition_rejected(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);
        $req->forceFill(['status' => 'menunggu_cetak'])->save();

        Sanctum::actingAs($this->makeStaff('admin'));

        // menunggu_cetak → siap_diambil skips sudah_dicetak.
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'siap_diambil'])->assertStatus(422);
        $this->assertSame('menunggu_cetak', $req->refresh()->status);
    }

    public function test_pickup_branch_rejected_for_delivery_request(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);
        $req->forceFill(['status' => 'menunggu_cetak', 'delivery_method' => 'delivery'])->save();

        Sanctum::actingAs($this->makeStaff('finance'));

        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'sudah_dicetak'])->assertStatus(200);
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'siap_diambil'])->assertStatus(422);
    }

    public function test_rejection_requires_reason(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);

        Sanctum::actingAs($this->makeStaff('admin'));

        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'ditolak'])->assertStatus(422);
        $this->putJson("/api/kta/print-requests/{$req->id}/status", [
            'status' => 'ditolak', 'reason' => 'Foto tidak sesuai standar',
        ])->assertStatus(200);

        $req->refresh();
        $this->assertSame('ditolak', $req->status);
        $this->assertSame('Foto tidak sesuai standar', $req->rejection_reason);
    }

    public function test_admin_cannot_force_payment_transition(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);

        Sanctum::actingAs($this->makeStaff('admin'));

        // menunggu_pembayaran → menunggu_cetak is payment-driven only.
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'menunggu_cetak'])->assertStatus(422);
    }

    public function test_admin_response_masks_pii(): void
    {
        $u = $this->makeMember(['name' => 'Achmad Hasanudin', 'id_anggota' => '0174011119']);
        $req = $this->makePending($u->id);
        $req->forceFill(['status' => 'menunggu_cetak'])->save();

        Sanctum::actingAs($this->makeStaff('finance'));
        $res = $this->getJson('/api/kta/print-requests')->assertStatus(200);

        $body = $res->getContent();
        $this->assertStringNotContainsString('Achmad', $body);
        $this->assertStringNotContainsString('0174011119', $body);
        $this->assertSame('A*** H***', $res->json('data.data.0.nama_masked'));
        $this->assertSame('MZT***119', $res->json('data.data.0.id_anggota_masked'));
    }

    // ─────────────────────────── public status ──────────────────────────────

    public function test_public_can_read_own_active_request(): void
    {
        $u = $this->makeMember();
        $this->makePending($u->id);

        $this->getJson('/api/public/kta/print-request?print_token=' . urlencode($this->printToken($u->id)))
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['request' => ['status' => 'menunggu_pembayaran']]]);
    }

    // ─────────────────────────── audit ──────────────────────────────────────

    public function test_audit_trail_records_each_transition(): void
    {
        $u = $this->makeMember();
        $req = $this->makePending($u->id);
        $req->forceFill(['status' => 'menunggu_cetak'])->save();

        Sanctum::actingAs($this->makeStaff('finance'));
        $this->putJson("/api/kta/print-requests/{$req->id}/status", ['status' => 'sudah_dicetak'])->assertStatus(200);

        $this->assertDatabaseHas('kta_print_request_logs', [
            'kta_print_request_id' => $req->id,
            'old_status' => 'menunggu_cetak',
            'new_status' => 'sudah_dicetak',
            'source' => 'admin',
        ]);
    }
}
