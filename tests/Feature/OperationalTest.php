<?php

namespace Tests\Feature;

use App\Models\HakAksesRole;
use App\Models\Prisensi_kehadiran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2D — EMS Operational Management backend regression.
 *
 * Covers the OperationalController / DashboardQuery read model for:
 *  - GET /dashboard/operations/events
 *  - GET /dashboard/operations/events/{event}/attendees
 *  - GET /dashboard/operations/events/{event}/attendance
 *  - GET /dashboard/operations/events/{event}/gates
 *
 * Canonical present = `prisensi_kehadiran.id_ticket IS NOT NULL`; legacy rows
 * are counted separately and labelled, never merged. DB::statement-based
 * schema (production migration chain depends on a prod-only column and cannot
 * run on a fresh test database).
 */
class OperationalTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate(['users', 'personal_access_tokens', 'hak_akses_role', 'events', 'prisensi_kehadiran', 'tickets']);
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('prisensi_kehadiran');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('events');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->default('');
            $table->string('id_anggota')->nullable()->index();
            $table->string('is_active')->default('1');
            $table->string('remember_token', 100)->nullable();
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

        // Mirrors production `events` columns actually used by the read model.
        Schema::create('events', function ($table) {
            $table->id();
            $table->string('judul_event');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('lokasi')->nullable();
            $table->integer('kuota')->nullable();
            $table->timestamps();
        });

        // Mirrors production `prisensi_kehadiran` (11 columns, source of truth).
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

        // `tickets` referenced via prisensi_kehadiran.id_ticket (Phase 2C).
        Schema::create('tickets', function ($table) {
            $table->id();
            $table->string('nomor_ticket', 50)->nullable();
            $table->string('status', 30)->default('issued');
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
        $user = User::factory()->create();
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
        ]);

        return $user;
    }

    private function seedEvent(int $id): void
    {
        DB::table('events')->insert([
            'id' => $id,
            'judul_event' => 'Event '.$id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-02',
            'lokasi' => 'Aula',
            'kuota' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAttendance(int $eventId, string $idAnggota, ?int $idTicket = null, ?string $gate = null): void
    {
        Prisensi_kehadiran::create([
            'id_event' => $eventId,
            'id_tanggal' => 1,
            'id_anggota' => $idAnggota,
            'id_ticket' => $idTicket,
            'gate' => $gate,
            'jam_kehadiran' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedTicket(int $id, string $status = 'issued'): void
    {
        DB::table('tickets')->insert([
            'id' => $id,
            'nomor_ticket' => 'TCK-'.$id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /* --------------------------------------------- authorization matrix */

    public function test_operations_endpoints_unauthenticated_is_401(): void
    {
        $this->getJson('/api/dashboard/operations/events')->assertStatus(401);
        $this->getJson('/api/dashboard/operations/events/1/attendees')->assertStatus(401);
        $this->getJson('/api/dashboard/operations/events/1/attendance')->assertStatus(401);
        $this->getJson('/api/dashboard/operations/events/1/gates')->assertStatus(401);
    }

    public function test_operations_endpoints_alumni_is_403(): void
    {
        $alumni = $this->makeUser('anggota');
        Sanctum::actingAs($alumni);

        $this->getJson('/api/dashboard/operations/events')->assertStatus(403);
        $this->getJson('/api/dashboard/operations/events/1/attendees')->assertStatus(403);
        $this->getJson('/api/dashboard/operations/events/1/attendance')->assertStatus(403);
        $this->getJson('/api/dashboard/operations/events/1/gates')->assertStatus(403);
    }

    public function test_operations_events_staff_roles_are_200(): void
    {
        $this->seedEvent(1);

        foreach (['dashboard', 'event', 'finance', 'ketua', 'admin'] as $role) {
            $user = $this->makeUser($role);
            Sanctum::actingAs($user);

            $this->getJson('/api/dashboard/operations/events')
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }

    /* --------------------------------------------- present vs legacy */

    public function test_events_aggregate_present_vs_legacy_separate(): void
    {
        $this->seedEvent(1);
        $this->seedEvent(2);

        // Event 1: 2 present + 3 legacy
        $this->seedAttendance(1, '100001', 111, 'A');
        $this->seedAttendance(1, '100002', 222, 'B');
        $this->seedAttendance(1, '100003', null, 'A');
        $this->seedAttendance(1, '100004', null, null);
        $this->seedAttendance(1, '100005', null, 'C');

        // Event 2: 0 present, 1 legacy
        $this->seedAttendance(2, '200001', null, 'A');

        $user = $this->makeUser('dashboard');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/operations/events')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $rows = $response->json('data');
        $event1 = collect($rows)->firstWhere('id_event', 1);
        $event2 = collect($rows)->firstWhere('id_event', 2);

        $this->assertSame(2, $event1['present_count']);
        $this->assertSame(3, $event1['legacy_count']);
        $this->assertSame(3, $event1['gate_count']); // gates A, B, C; NULL gate ignored by COUNT(DISTINCT)
        $this->assertSame(0, $event2['present_count']);
        $this->assertSame(1, $event2['legacy_count']);
    }

    public function test_attendance_summary_present_legacy_split(): void
    {
        $this->seedEvent(1);
        $this->seedAttendance(1, '100001', 111, 'A');
        $this->seedAttendance(1, '100002', null, 'B');
        $this->seedAttendance(1, '100003', null, 'A');

        $user = $this->makeUser('dashboard');
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/operations/events/1/attendance')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.present', 1)
            ->assertJsonPath('data.legacy_count', 2)
            ->assertJsonPath('data.total', 3);
    }

    public function test_gate_monitoring_groups_by_gate(): void
    {
        $this->seedEvent(1);
        $this->seedAttendance(1, '100001', 111, 'A');
        $this->seedAttendance(1, '100002', null, 'A');
        $this->seedAttendance(1, '100003', null, 'B');
        $this->seedAttendance(1, '100004', null, null);

        $user = $this->makeUser('dashboard');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/operations/events/1/gates')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data.rows'));
        $gateA = $rows->firstWhere('gate', 'A');
        $gateB = $rows->firstWhere('gate', 'B');
        $ungated = $rows->firstWhere('gate', '(ungated)');

        $this->assertSame(1, $gateA['present']);
        $this->assertSame(1, $gateA['legacy']); // canonical key `legacy`
        $this->assertSame(1, $gateB['legacy']);
        $this->assertSame(1, $ungated['legacy']);

        $breakdown = $response->json('data.breakdown_per_gate');
        $this->assertSame(['present' => 1, 'legacy' => 1, 'total' => 2], $breakdown['A']);
        $this->assertSame(['present' => 0, 'legacy' => 1, 'total' => 1], $breakdown['B']);
        $this->assertSame(['present' => 0, 'legacy' => 1, 'total' => 1], $breakdown['(ungated)']);
    }

    /* --------------------------------------------- participants */

    public function test_attendees_pagination_meta(): void
    {
        $this->seedEvent(1);
        for ($i = 1; $i <= 30; $i++) {
            $this->seedAttendance(1, sprintf('10%04d', $i), $i, 'A');
        }

        $user = $this->makeUser('finance');
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/operations/events/1/attendees?per_page=10&page=2')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 30)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.page', 2)
            ->assertJsonPath('data.meta.last_page', 3)
            ->assertJsonPath('data.meta.filter.event_id', 1)
            ->assertJsonPath('data.meta.filter.tgl', null)
            ->assertJsonPath('data.meta.filter.gate', null)
            ->assertJsonCount(10, 'data.rows');
    }

    public function test_attendees_pii_masked_for_non_verifier(): void
    {
        $this->seedEvent(1);
        DB::table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '',
            'id_anggota' => '100001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTicket(111, 'issued');
        $this->seedAttendance(1, '100001', 111, 'A');

        $staff = $this->makeUser('dashboard');
        Sanctum::actingAs($staff);

        $json = $this->getJson('/api/dashboard/operations/events/1/attendees')
            ->assertStatus(200)
            ->assertJsonPath('data.rows.0.gate', 'A')
            ->assertJsonPath('data.rows.0.source', 'phase2c')
            ->assertJsonPath('data.rows.0.ticket_status', 'issued')
            ->json();

        // §10 PII matrix: non-verifier must NOT receive nama nor id_anggota.
        $this->assertNull($json['data']['rows'][0]['nama']);
        $this->assertNull($json['data']['rows'][0]['id_anggota']);
        // Guard: the member name string must not appear anywhere in the payload.
        $this->assertStringNotContainsString('John Doe', json_encode($json));
    }

    public function test_attendees_pii_visible_for_verifier(): void
    {
        $this->seedEvent(1);
        DB::table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '',
            'id_anggota' => '100001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTicket(111, 'issued');
        $this->seedAttendance(1, '100001', 111, 'A');

        $finance = $this->makeUser('finance');
        Sanctum::actingAs($finance);

        $this->getJson('/api/dashboard/operations/events/1/attendees')
            ->assertStatus(200)
            ->assertJsonPath('data.rows.0.nama', 'John Doe')
            ->assertJsonPath('data.rows.0.id_anggota', '100001')
            ->assertJsonPath('data.rows.0.ticket_status', 'issued');
    }

    public function test_attendees_orphan_flag_and_search(): void
    {
        $this->seedEvent(1);
        DB::table('users')->insert([
            'name' => 'Jane Member',
            'email' => 'jane@example.com',
            'password' => '',
            'id_anggota' => '888001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedAttendance(1, '888001', 111, 'A');
        $this->seedAttendance(1, '999999', null, 'B');

        $finance = $this->makeUser('finance');
        Sanctum::actingAs($finance);

        $this->getJson('/api/dashboard/operations/events/1/attendees')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 2);

        $response = $this->getJson('/api/dashboard/operations/events/1/attendees?q=999999')
            ->assertStatus(200);
        $this->assertSame(1, $response->json('data.meta.total'));
        $this->assertSame('orphan', $response->json('data.rows.0.account_status'));

        $response = $this->getJson('/api/dashboard/operations/events/1/attendees?q=Jane')
            ->assertStatus(200);
        $this->assertSame(1, $response->json('data.meta.total'));
        $this->assertSame('normal', $response->json('data.rows.0.account_status'));
    }

    /* --------------------------------------------- query-count evidence */

    public function test_events_endpoint_query_count_is_constant(): void
    {
        $this->seedEvent(1);
        $this->seedAttendance(1, '100001', 111, 'A');

        $user = $this->makeUser('dashboard');
        $user->is_active = '1'; // in-memory attr avoids an extra active-check query
        Sanctum::actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/dashboard/operations/events')->assertStatus(200);

        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
    }

    public function test_attendees_endpoint_query_count_is_constant(): void
    {
        $this->seedEvent(1);
        for ($i = 1; $i <= 25; $i++) {
            $this->seedAttendance(1, sprintf('20%04d', $i), $i, 'A');
        }

        $user = $this->makeUser('finance');
        $user->is_active = '1'; // in-memory attr avoids an extra active-check query
        Sanctum::actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/dashboard/operations/events/1/attendees')->assertStatus(200);

        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
    }
}