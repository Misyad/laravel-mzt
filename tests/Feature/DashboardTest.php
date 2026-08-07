<?php

namespace Tests\Feature;

use App\Models\HakAksesRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sprint 5A — Dashboard regression & performance coverage.
 *
 * Guarantees:
 *  - Empty Dataset: every endpoint still returns success:true with zero values
 *    (no 500, no empty-state errors).
 *  - Large Dataset (500 orders / 500 payments / 500 tickets): endpoints stay
 *    correct and within the performance gate (< 500 ms per endpoint).
 *  - Authorization: finance user → 200; staff non-finance → 403; no token → 401.
 *
 * NOTE: the test builds only the tables the Dashboard read model touches. The
 * legacy production migration chain depends on a prod-only `events.harga`
 * column and cannot run on a fresh database, so a full RefreshDatabase is not
 * used here. Schema is created once and truncated between tests.
 */
class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    private const PERFORMANCE_GATE_MS = 500;

    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate(['orders', 'payments', 'tickets', 'users', 'hak_akses_role', 'personal_access_tokens']);
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('id_anggota')->nullable()->index();
            $table->string('is_active')->default('1');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hak_akses_role', function ($table) {
            $table->id();
            $table->integer('id_users');
            $table->string('nama_role');
            $table->enum('hak_akses', ['access', 'no_accesss'])->default('access');
            $table->timestamps();
        });

        Schema::create('orders', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_order', 20)->unique();
            $table->unsignedBigInteger('id_event')->index();
            $table->string('id_anggota')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('event_name');
            $table->decimal('event_price', 12, 2)->default(0);
            $table->date('event_start_at')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status_registrasi', 30)->default('draft');
            $table->string('payment_status', 30)->default('pending');
            $table->timestamps();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_payment', 30)->unique();
            $table->unsignedBigInteger('id_order');
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('id_order');
            $table->index('status');
        });

        Schema::create('tickets', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_ticket', 30)->unique();
            $table->unsignedBigInteger('id_order');
            $table->string('qr_payload');
            $table->string('status', 30)->default('issued');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('id_order');
            $table->index('status');
        });
    }

    private function truncate(array $tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
        ]);

        return $user;
    }

    private function seedDomain(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order = Order::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'nomor_order' => 'MZT-TEST-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'id_event' => $i + 1,
                'id_anggota' => (string) ($i + 1),
                'event_name' => 'Event ' . $i,
                'event_price' => 100_000,
                'event_start_at' => '2026-08-01',
                'total_amount' => 100_000,
                'status_registrasi' => match ($i % 3) {
                    0 => 'draft',
                    1 => 'registered',
                    default => 'confirmed',
                },
                'payment_status' => 'pending',
            ]);

            Payment::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'nomor_payment' => 'PAY-TEST-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'id_order' => $order->id,
                'method' => 'transfer',
                'amount' => 100_000,
                'status' => $i % 5 === 0 ? 'waiting_verification' : 'paid',
                'paid_at' => now(),
                'verified_at' => now(),
            ]);

            Ticket::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'nomor_ticket' => 'TKT-TEST-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'id_order' => $order->id,
                'qr_payload' => (string) \Illuminate\Support\Str::uuid(),
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        }
    }

    public function test_endpoints_are_read_only_and_authorized(): void
    {
        $finance = $this->makeUser('finance');
        Sanctum::actingAs($finance);

        $this->getJson('/api/dashboard/finance/overview')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_empty_dataset_returns_success_with_zero_values(): void
    {
        $finance = $this->makeUser('finance');
        Sanctum::actingAs($finance);

        $this->getJson('/api/dashboard/finance/overview')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => [
                'total_orders' => 0,
                'total_revenue' => 0,
                'total_paid' => 0,
                'total_outstanding' => 0,
                'total_tickets' => 0,
                'pending_verifications' => 0,
            ]]);

        $this->getJson('/api/dashboard/finance/registration')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['total_orders' => 0, 'by_status' => []]]);

        $this->getJson('/api/dashboard/finance/revenue')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => [
                'total_revenue' => 0, 'total_paid' => 0, 'outstanding' => 0, 'by_status' => [],
            ]]);

        $this->getJson('/api/dashboard/finance/payments')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => [
                'by_status' => [], 'waiting_verification' => 0,
            ]]);
    }

    /**
     * @group performance
     */
    public function test_large_dataset_meets_performance_gate(): void
    {
        $this->seedDomain(500);

        $finance = $this->makeUser('finance');
        Sanctum::actingAs($finance);

        $start = microtime(true);
        $overview = $this->getJson('/api/dashboard/finance/overview');
        $overviewTime = round((microtime(true) - $start) * 1000);

        $overview->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total_orders', 500)
            ->assertJsonPath('data.total_tickets', 500);

        $this->assertLessThan(self::PERFORMANCE_GATE_MS, $overviewTime, "Overview took {$overviewTime}ms");

        $start = microtime(true);
        $revenue = $this->getJson('/api/dashboard/finance/revenue');
        $revenueTime = round((microtime(true) - $start) * 1000);

        $revenue->assertStatus(200)->assertJson(['success' => true]);
        $this->assertLessThan(self::PERFORMANCE_GATE_MS, $revenueTime, "Revenue took {$revenueTime}ms");
    }

    public function test_staff_non_finance_is_forbidden_for_finance_endpoints(): void
    {
        $staff = $this->makeUser('dashboard');
        Sanctum::actingAs($staff);

        $this->getJson('/api/dashboard/finance/overview')->assertStatus(200);
        $this->getJson('/api/dashboard/finance/revenue')->assertStatus(403);
        $this->getJson('/api/dashboard/finance/payments')->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/dashboard/finance/overview')->assertStatus(401);
    }
}