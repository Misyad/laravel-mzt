<?php

namespace Tests\Feature;

use App\Events\TicketStatusChanged;
use App\Models\HakAksesRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CommunicationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2C — QR Check-in endpoint (POST /api/checkin).
 *
 * NOTE: this class deliberately does NOT use DatabaseTransactions — proving
 * that DB::afterCommit really executes requires the service transaction to
 * actually commit. Schema is built manually once and truncated between tests
 * (DashboardTest pattern; a full RefreshDatabase is impossible because the
 * legacy prod migration chain depends on a prod-only `events.harga` column).
 */
class CheckInTest extends TestCase
{
    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate([
            'prisensi_kehadiran',
            'ticket_logs',
            'tickets',
            'payment_logs',
            'payments',
            'orders',
            'tanggal_events',
            'hak_akses_role',
            'users',
            'personal_access_tokens',
        ]);
        $this->seedActiveRoleCatalog(['anggota', 'dashboard', 'event', 'finance', 'prisensi', 'ketua', 'admin']);
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('prisensi_kehadiran');
        Schema::dropIfExists('ticket_logs');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('tanggal_events');
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
            $table->timestamp('password_changed_at')->nullable();
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
            $table->string('payment_choice', 30)->default('pay_now');
            $table->timestamps();
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_payment', 30)->unique();
            $table->unsignedBigInteger('id_order');
            $table->string('method', 30);
            $table->string('source', 30)->nullable();
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
        });

        Schema::create('payment_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_payment');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
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

        Schema::create('ticket_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_ticket');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['id_ticket', 'created_at']);
        });

        Schema::create('tanggal_events', function ($table) {
            $table->id();
            $table->integer('id_event');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->string('jam_selesai')->nullable();
            $table->enum('set_jam', ['seharian', 'dijam'])->default('seharian');
            $table->timestamps();
        });

        Schema::create('prisensi_kehadiran', function ($table) {
            $table->id();
            $table->integer('id_event');
            $table->bigInteger('id_tanggal')->nullable();
            $table->string('id_anggota')->default('');
            $table->bigInteger('id_user')->default(0);
            $table->timestamp('tanggal_kehadiran')->nullable()->useCurrent();
            $table->timestamp('jam_kehadiran')->useCurrent();
            $table->unsignedBigInteger('id_ticket')->nullable();
            $table->string('gate', 100)->nullable();
            $table->dateTime('scanned_at')->nullable();
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->timestamps();
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
        $user = User::factory()->create(['password_changed_at' => now()]);
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
        ]);

        return $user;
    }

    /**
     * Seed a participant + order + one event day + a ticket with the given status.
     *
     * @return array{ticket: Ticket, order: Order, tanggalId: int, participant: User}
     */
    private function seedDomain(
        string $status = 'issued',
        string $paymentStatus = 'paid',
        string $paymentChoice = 'pay_now'
    ): array {
        $participant = User::factory()->create([
            'id_anggota' => (string) random_int(100_000, 999_999),
        ]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'nomor_order' => 'ORD-' . Str::upper(Str::random(8)),
            'id_event' => 1,
            'id_anggota' => $participant->id_anggota,
            'event_name' => 'MZT Gathering',
            'event_price' => 100_000,
            'event_start_at' => '2026-08-01',
            'total_amount' => 100_000,
            'status_registrasi' => 'confirmed',
            'payment_status' => $paymentStatus,
            'payment_choice' => $paymentChoice,
        ]);

        if ($paymentStatus === 'paid') {
            Payment::create([
                'uuid' => (string) Str::uuid(),
                'nomor_payment' => 'PAY-' . Str::upper(Str::random(8)),
                'id_order' => $order->id,
                'method' => 'transfer',
                'source' => 'verified_transfer',
                'amount' => 100_000,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        DB::table('tanggal_events')->insert([
            'id_event' => 1,
            'tanggal' => '2026-08-09',
            'set_jam' => 'seharian',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tanggalId = (int) DB::getPdo()->lastInsertId();

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'nomor_ticket' => 'TKT-' . Str::upper(Str::random(8)),
            'id_order' => $order->id,
            'qr_payload' => (string) Str::uuid(),
            'status' => $status,
            'issued_at' => now(),
        ]);

        return compact('ticket', 'order', 'tanggalId', 'participant');
    }

    private function payload(string $ticketUuid, int $tanggalId, array $extra = []): array
    {
        return array_merge([
            'ticket_uuid' => $ticketUuid,
            'id_tanggal' => $tanggalId,
            'gate' => 'Gate A',
        ], $extra);
    }

    /* ---------------------------------------------------------- authorization */

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/checkin', [])->assertStatus(401);
    }

    public function test_alumni_is_forbidden(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('anggota');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(403);
    }

    public function test_prisensi_role_can_check_in(): void
    {
        $this->assertRoleCanCheckIn('prisensi');
    }

    public function test_event_role_can_check_in(): void
    {
        $this->assertRoleCanCheckIn('event');
    }

    public function test_finance_role_can_check_in(): void
    {
        $this->assertRoleCanCheckIn('finance');
    }

    private function assertRoleCanCheckIn(string $role): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser($role);
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /* --------------------------------------------------------------- input */

    public function test_invalid_uuid_is_422(): void
    {
        $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', [
            'ticket_uuid' => 'not-a-uuid',
            'id_tanggal' => 1,
        ])->assertStatus(422);
    }

    public function test_unknown_ticket_is_404(): void
    {
        $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', $this->payload((string) Str::uuid(), 1))
            ->assertStatus(404);
    }

    public function test_tanggal_not_belonging_to_event_is_422(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        // tanggal 999 does not belong to the ticket's event (event 1).
        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, 999))
            ->assertStatus(422);
    }

    /* ----------------------------------------------------------- state rules */

    public function test_rejected_ticket_statuses_are_409(): void
    {
        foreach (['draft', 'cancelled', 'revoked', 'finished'] as $status) {
            $seed = $this->seedDomain($status);
            $operator = $this->makeUser('prisensi');
            Sanctum::actingAs($operator);

            $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
                ->assertStatus(409);
        }
    }

    public function test_scanner_lookup_is_read_only_and_supports_ticket_number(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin/lookup', [
            'identifier' => $seed['ticket']->nomor_ticket,
            'id_event' => 1,
            'id_tanggal' => $seed['tanggalId'],
        ])->assertStatus(200)
            ->assertJsonPath('data.ticket.uuid', $seed['ticket']->uuid)
            ->assertJsonPath('data.participant.name', $seed['participant']->name)
            ->assertJsonPath('data.event.event_name', 'MZT Gathering')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.payment.amount', 100000)
            ->assertJsonPath('data.attendance.status', 'not_present');

        $this->assertSame('issued', DB::table('tickets')->where('id', $seed['ticket']->id)->value('status'));
        $this->assertSame(0, DB::table('prisensi_kehadiran')->count());
        $this->assertSame(0, DB::table('ticket_logs')->count());
    }

    public function test_legacy_paid_order_without_payment_ledger_can_be_looked_up_and_checked_in(): void
    {
        $seed = $this->seedDomain('issued');
        Payment::where('id_order', $seed['order']->id)->delete();
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $lookup = [
            'identifier' => $seed['ticket']->uuid,
            'id_event' => 1,
            'id_tanggal' => $seed['tanggalId'],
        ];

        $this->postJson('/api/checkin/lookup', $lookup)
            ->assertStatus(200)
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.payment.amount', 100000)
            ->assertJsonPath('data.payment.source', null)
            ->assertJsonPath('data.attendance.status', 'not_present');

        $this->assertSame(0, DB::table('prisensi_kehadiran')->count());

        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(200)
            ->assertJsonPath('data.ticket.status', 'checked_in');

        $this->assertSame(1, DB::table('prisensi_kehadiran')->count());
    }

    public function test_scanner_lookup_rejects_wrong_event_without_writes(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('event');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin/lookup', [
            'identifier' => $seed['ticket']->uuid,
            'id_event' => 999,
            'id_tanggal' => $seed['tanggalId'],
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('prisensi_kehadiran')->count());
    }

    public function test_unpaid_pay_now_ticket_cannot_check_in(): void
    {
        $seed = $this->seedDomain('issued', 'pending', 'pay_now');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Pembayaran belum lunas');

        $this->assertSame(0, DB::table('prisensi_kehadiran')->count());
    }

    public function test_onsite_amount_must_match_server_outstanding_and_rolls_back(): void
    {
        $seed = $this->seedDomain('issued', 'pending', 'pay_at_venue');
        $operator = $this->makeUser('event');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin/onsite', [
            'ticket_uuid' => $seed['ticket']->uuid,
            'id_tanggal' => $seed['tanggalId'],
            'gate' => 'Gate B',
            'amount' => 50_000,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Nominal pembayaran tidak sesuai sisa tagihan');

        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('prisensi_kehadiran')->count());
        $this->assertSame('issued', DB::table('tickets')->where('id', $seed['ticket']->id)->value('status'));
    }

    public function test_onsite_payment_and_attendance_are_atomic_and_audited(): void
    {
        $seed = $this->seedDomain('issued', 'pending', 'pay_at_venue');
        $operator = $this->makeUser('event');
        Sanctum::actingAs($operator);

        $payload = [
            'ticket_uuid' => $seed['ticket']->uuid,
            'id_tanggal' => $seed['tanggalId'],
            'gate' => 'Gate B',
            'amount' => 100_000,
        ];

        $this->postJson('/api/checkin/onsite', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.payment.source', 'on_site')
            ->assertJsonPath('data.attendance.status', 'present');

        $this->assertDatabaseHas('payments', [
            'id_order' => $seed['order']->id,
            'method' => 'cash',
            'source' => 'on_site',
            'amount' => 100_000,
            'status' => 'paid',
            'verified_by' => $operator->id,
        ]);
        $payment = Payment::where('id_order', $seed['order']->id)->firstOrFail();
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('payment_logs', [
            'id_payment' => $payment->id,
            'old_status' => 'pending',
            'new_status' => 'paid',
            'note' => 'on_site',
            'changed_by' => $operator->id,
        ]);
        $this->assertDatabaseHas('prisensi_kehadiran', [
            'id_ticket' => $seed['ticket']->id,
            'gate' => 'Gate B',
            'scanned_by' => $operator->id,
        ]);
        $this->assertSame('paid', DB::table('orders')->where('id', $seed['order']->id)->value('payment_status'));
        $this->assertSame('checked_in', DB::table('tickets')->where('id', $seed['ticket']->id)->value('status'));

        $this->postJson('/api/checkin/onsite', $payload)->assertStatus(409);
        $this->assertSame(1, DB::table('payments')->count());
        $this->assertSame(1, DB::table('prisensi_kehadiran')->count());
    }

    /* ------------------------------------------------------------ happy path */

    public function test_valid_issued_ticket_checks_in_with_full_audit(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Check-in berhasil',
            ])
            ->assertJsonPath('data.ticket.uuid', $seed['ticket']->uuid)
            ->assertJsonPath('data.ticket.status', 'checked_in')
            ->assertJsonPath('data.event.event_name', 'MZT Gathering')
            ->assertJsonPath('data.participant.name', $seed['participant']->name)
            ->assertJsonPath('data.attendance.gate', 'Gate A')
            ->assertJsonPath('data.attendance.scanned_by', $operator->id);

        // 15 + 16 — attendance created with all check-in fields filled.
        $this->assertDatabaseHas('prisensi_kehadiran', [
            'id_event' => 1,
            'id_tanggal' => $seed['tanggalId'],
            'id_anggota' => $seed['participant']->id_anggota,
            'id_ticket' => $seed['ticket']->id,
            'gate' => 'Gate A',
            'scanned_by' => $operator->id,
        ]);
        $attendance = DB::table('prisensi_kehadiran')->where('id_ticket', $seed['ticket']->id)->first();
        $this->assertNotNull($attendance->scanned_at);

        // Ticket flipped with used_at set.
        $ticket = DB::table('tickets')->where('id', $seed['ticket']->id)->first();
        $this->assertSame('checked_in', $ticket->status);
        $this->assertNotNull($ticket->used_at);

        // 17 — audit log appended.
        $this->assertDatabaseHas('ticket_logs', [
            'id_ticket' => $seed['ticket']->id,
            'old_status' => 'issued',
            'new_status' => 'checked_in',
            'note' => 'check_in',
            'changed_by' => $operator->id,
        ]);
    }

    public function test_duplicate_scan_is_409_with_first_scan_info(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $payload = $this->payload($seed['ticket']->uuid, $seed['tanggalId']);

        $this->postJson('/api/checkin', $payload)->assertStatus(200);

        $duplicate = $this->postJson('/api/checkin', $payload)
            ->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Tiket sudah digunakan'])
            ->assertJsonPath('data.first_scanned_by', $operator->id);
        $this->assertNotNull($duplicate->json('data.first_scanned_at'));

        // 20 — a duplicate adds no attendance, no log, and does not touch the ticket.
        $this->assertSame(1, DB::table('prisensi_kehadiran')->count());
        $this->assertSame(1, DB::table('ticket_logs')->count());
        $this->assertSame('checked_in', DB::table('tickets')->where('id', $seed['ticket']->id)->value('status'));
    }

    public function test_ticket_status_changed_dispatched_after_commit(): void
    {
        Event::fake([TicketStatusChanged::class]);

        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(200);

        Event::assertDispatched(TicketStatusChanged::class, function (TicketStatusChanged $event) use ($seed) {
            return $event->ticket->id === $seed['ticket']->id
                && $event->action === 'check_in'
                && $event->oldStatus === 'issued'
                && $event->newStatus === 'checked_in';
        });
    }

    public function test_communication_dispatcher_is_not_called_for_check_in(): void
    {
        $this->mock(CommunicationDispatcher::class, function ($mock) {
            $mock->shouldNotReceive('dispatch');
        });

        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        // Real listener runs (event NOT faked); it must ignore action=check_in
        // until a communication template exists (Sprint 4, forward-only).
        $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
            ->assertStatus(200);
    }

    public function test_rollback_leaves_zero_artifacts(): void
    {
        $seed = $this->seedDomain('issued');
        $operator = $this->makeUser('prisensi');
        Sanctum::actingAs($operator);

        // Wrap the request in an outer transaction and force a rollback after
        // the service committed its inner transaction. All mutations must be
        // undone: no attendance, no log, ticket back to issued, used_at null.
        DB::beginTransaction();
        try {
            $this->postJson('/api/checkin', $this->payload($seed['ticket']->uuid, $seed['tanggalId']))
                ->assertStatus(200);
            throw new \RuntimeException('forced failure after check-in');
        } catch (\Throwable $e) {
            DB::rollBack();
        }

        $this->assertSame(0, DB::table('prisensi_kehadiran')->count());
        $this->assertSame(0, DB::table('ticket_logs')->count());
        $this->assertSame('issued', DB::table('tickets')->where('id', $seed['ticket']->id)->value('status'));
        $this->assertNull(DB::table('tickets')->where('id', $seed['ticket']->id)->value('used_at'));
    }
}
