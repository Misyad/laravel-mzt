<?php

namespace Tests\Feature;

use App\Models\HakAksesRole;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2D Gate Closure — M-01/M-02 legacy endpoint authorization.
 *
 *  - M-01  GET /api/attendance/{eventId}/{tanggalId}  → viewAttendance (isStaff)
 *  - M-02  GET /api/transactions/{eventId}            → viewTransactions (canVerify)
 *
 * Matrix asserted:
 *  - M-01: unauthenticated → 401; alumni → 403; staff (dashboard/event/finance/ketua/admin) → 200
 *  - M-02: unauthenticated → 401; non-verifier (staff/event/prisensi/alumni) → 403;
 *          finance/ketua/admin → 200
 *
 * Uses the DashboardTest buildSchema pattern (manual schema, no full RefreshDatabase
 * because the legacy prod migration chain depends on a prod-only `events.harga` column).
 */
class LegacyGateTest extends TestCase
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

        $this->truncate(['prisensi_kehadiran', 'm_transaksi_events', 'users', 'hak_akses_role', 'role_user', 'personal_access_tokens']);

        foreach (['anggota', 'dashboard', 'event', 'finance', 'prisensi', 'ketua', 'admin'] as $role) {
            RoleUser::create(['nama_role' => $role, 'is_active' => '1']);
        }
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('prisensi_kehadiran');
        Schema::dropIfExists('m_transaksi_events');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('id_anggota')->nullable()->unique();
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

        Schema::create('role_user', function ($table) {
            $table->id();
            $table->string('nama_role');
            $table->string('is_active')->default('1');
            $table->timestamps();
        });

        // Mirrors production `prisensi_kehadiran` (source of truth, C-02).
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

        // Mirrors production `m_transaksi_events`.
        Schema::create('m_transaksi_events', function ($table) {
            $table->id();
            $table->string('id_anggota');
            $table->string('order_id')->nullable();
            $table->string('gross_amount')->nullable();
            $table->string('payment_code')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('status_code')->nullable();
            $table->string('status_message')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('transaction_status')->nullable();
            $table->date('transaction_time')->nullable();
            $table->string('snaptoken')->nullable();
            $table->bigInteger('id_event');
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

    private function seedLegacyRows(): void
    {
        $idAnggota = (string) random_int(100_000, 999_999);
        $member = User::factory()->create(['id_anggota' => $idAnggota]);
        DB::table('prisensi_kehadiran')->insert([
            'id_event' => 1,
            'id_tanggal' => 1,
            'id_anggota' => $member->id_anggota,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('m_transaksi_events')->insert([
            'id_anggota' => $member->id_anggota,
            'id_event' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /* ---------------------------------------------------------- M-01 attendance */

    public function test_attendance_unauthenticated_is_401(): void
    {
        $this->getJson('/api/attendance/1/1')->assertStatus(401);
    }

    public function test_attendance_alumni_is_403(): void
    {
        $alumni = $this->makeUser('anggota');
        Sanctum::actingAs($alumni);

        $this->getJson('/api/attendance/1/1')->assertStatus(403);
    }

    public function test_attendance_staff_roles_are_200(): void
    {
        foreach (['dashboard', 'event', 'finance', 'ketua', 'admin'] as $role) {
            $this->seedLegacyRows();

            $user = $this->makeUser($role);
            Sanctum::actingAs($user);

            $this->getJson('/api/attendance/1/1')
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }

    /* ------------------------------------------------------- M-02 transactions */

    public function test_transactions_unauthenticated_is_401(): void
    {
        $this->getJson('/api/transactions/1')->assertStatus(401);
    }

    public function test_transactions_non_verifier_is_403(): void
    {
        foreach (['dashboard', 'event', 'prisensi', 'anggota'] as $role) {
            $user = $this->makeUser($role);
            Sanctum::actingAs($user);

            $this->getJson('/api/transactions/1')->assertStatus(403);
        }
    }

    public function test_transactions_verifier_roles_are_200(): void
    {
        foreach (['finance', 'ketua', 'admin'] as $role) {
            $this->seedLegacyRows();

            $user = $this->makeUser($role);
            Sanctum::actingAs($user);

            $this->getJson('/api/transactions/1')
                ->assertStatus(200)
                ->assertJson(['success' => true, 'data' => []]);
        }
    }
}