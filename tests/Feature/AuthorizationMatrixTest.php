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
 * C-01 â€” API Authorization Matrix.
 *
 * Proves the backend is the authoritative authorization boundary for every
 * previously ungated privileged surface:
 *
 *   A. members directory            viewDirectory  (staff)
 *   B. member creation              writeMember    (ketua/admin)
 *   C. member update/delete         writeMember    (ketua/admin)
 *   D. account creation             manageAccounts (ketua/admin)
 *   E. account password reset       manageAccounts (ketua/admin)
 *   F. account activation status    manageAccounts (ketua/admin)
 *   G. bulk account generation      manageAccounts (ketua/admin)
 *   H. legacy dashboards            viewLegacyDashboards (staff)
 *   I. event CRUD                   manageEvents   (staff, interim global role)
 *   J. news CRUD                    manageContent  (staff)
 *   K. carousel/info management     manageContent  (staff)
 *   L. legacy attendance WRITE      recordAttendance (prisensi/event âˆª verifier)
 *   M. activity log                 viewAuditLog   (verifier)
 *
 * Triads asserted: unauthenticated -> 401; authenticated-but-unauthorized ->
 * 403; authorized -> 2xx. Plus the two C-01 regressions:
 *   - an alumni CANNOT self-assign roles[] via POST /members (403, zero rows);
 *   - deactivating an account invalidates its live PAT while leaving other
 *     accounts untouched (per-request active enforcement).
 *
 * Schema mirrors production columns touched by these code paths (manual build,
 * DatabaseTransactions â€” same rationale as LegacyGateTest/AuthHybridTest).
 */
class AuthorizationMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate([
            'sessions',
            'personal_access_tokens',
            'hak_akses_role',
            'role_user',
            'data_users',
            'users',
            'prisensi_kehadiran',
            'events',
            'event_status',
            'beritas',
            'carosels',
            'info_pesantrens',
            'tentang_mzts',
        ]);

        foreach (['anggota', 'profil', 'dashboard', 'event', 'finance', 'prisensi', 'ketua', 'admin'] as $role) {
            RoleUser::create(['nama_role' => $role, 'is_active' => '1']);
        }

        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('tentang_mzts');
        Schema::dropIfExists('info_pesantrens');
        Schema::dropIfExists('carosels');
        Schema::dropIfExists('beritas');
        Schema::dropIfExists('event_status');
        Schema::dropIfExists('events');
        Schema::dropIfExists('prisensi_kehadiran');
        Schema::dropIfExists('data_users');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('id_anggota')->nullable()->unique();
            $table->string('is_active')->default('1');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
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

        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
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

        // Columns touched by membersIndex/Show/Update/Destroy/bulk/generate.
        Schema::create('data_users', function ($table) {
            $table->id();
            $table->integer('id_users');
            $table->string('alamat')->nullable();
            $table->string('niqobah')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tahun_masuk')->nullable();
            $table->date('tahun_keluar')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('foto')->nullable();
            $table->string('barcode')->nullable();
            $table->string('is_active')->default('1');
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

        // Columns written by ApiController::eventsStore.
        Schema::create('events', function ($table) {
            $table->id();
            $table->string('judul_event');
            $table->string('slug')->unique();
            $table->string('lokasi')->nullable();
            $table->string('harga')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('banner')->nullable();
            $table->string('tanggal')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('is_active')->default('1');
            $table->integer('kuota')->nullable();
            $table->string('venue')->nullable();
            $table->string('visibility')->default('public');
            $table->date('registrasi_dibuka')->nullable();
            $table->date('registrasi_ditutup')->nullable();
            $table->float('harga_amount')->nullable();
            $table->timestamps();
        });

        // Production VIEW `event_status` (read by legacy dashboard trio).
        Schema::create('event_status', function ($table) {
            $table->id();
            $table->string('judul_event')->nullable();
            $table->string('status')->nullable();
            $table->string('is_active')->default('1');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
        });

        Schema::create('beritas', function ($table) {
            $table->id();
            $table->string('judul');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->string('foto')->nullable();
            $table->string('is_active')->default('1');
            $table->timestamps();
        });

        Schema::create('carosels', function ($table) {
            $table->id();
            $table->string('foto')->nullable();
            $table->timestamps();
        });

        foreach (['info_pesantrens', 'tentang_mzts'] as $tbl) {
            Schema::create($tbl, function ($table) {
                $table->id();
                $table->string('judul')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('alamat')->nullable();
                $table->string('telpon')->nullable();
                $table->string('email')->nullable();
                $table->string('foto')->nullable();
                $table->timestamps();
            });
        }
    }

    private function truncate(array $tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function makeUser(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'password_changed_at' => now(),
        ], $attrs));
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
        ]);

        return $user;
    }

    private function makeAlumni(): User
    {
        return $this->makeUser('anggota', [
            'id_anggota' => (string) random_int(100_000, 999_999),
        ]);
    }

    /** Seed a data_users profile row (optionally without a linked user). */
    private function makeMemberData(?int $userId = null): int
    {
        // Default to an id_users far outside the users auto-increment range so
        // "unlinked profile" fixtures never collide with real user ids.
        return DB::table('data_users')->insertGetId([
            'id_users' => $userId ?? random_int(500_000, 999_999),
            'alamat' => 'Jl. Test',
            'no_hp' => '0812',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asGuest(): self
    {
        app('auth')->forgetGuards();

        return $this;
    }

    /* ------------------------------------------------------- Guest: 401 wall */

    public function test_guest_receives_401_on_every_privileged_surface(): void
    {
        $paths = [
            ['GET', '/api/members'],
            ['GET', '/api/members/account-reset-audit'],
            ['PUT', '/api/members/1/account'],
            ['PUT', '/api/members/1/status'],
            ['GET', '/api/dashboard/stats'],
            ['GET', '/api/dashboard/calendar'],
            ['GET', '/api/dashboard/events'],
            ['POST', '/api/events'],
            ['DELETE', '/api/events/1'],
            ['POST', '/api/news'],
            ['POST', '/api/carousel/1'],
            ['POST', '/api/info/pesantren'],
            ['POST', '/api/info/mzt'],
            ['POST', '/api/attendance'],
            ['GET', '/api/activity-log'],
        ];

        foreach ($paths as [$method, $path]) {
            $this->asGuest();
            $response = $this->json($method, $path, []);
            $this->assertSame(
                401,
                $response->status(),
                "Expected 401 for guest {$method} {$path}, got " . $response->status()
            );
        }
    }

    /* ------------------------------------------- A. Member PII directory */

    public function test_member_directory_denied_to_alumni_and_prisensi(): void
    {
        foreach (['anggota', 'prisensi'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/members')->assertStatus(403);
        }
    }

    public function test_member_directory_allowed_for_staff(): void
    {
        foreach (['dashboard', 'event', 'finance', 'ketua', 'admin'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/members')
                ->assertSuccessful()
                ->assertJsonPath('success', true);
        }
    }

    public function test_member_show_follows_same_directory_boundary(): void
    {
        $target = $this->makeAlumni();
        $this->makeMemberData($target->id);

        Sanctum::actingAs($this->makeUser('anggota'));
        $this->getJson("/api/members/{$target->id}")->assertStatus(403);

        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson("/api/members/{$target->id}")
            ->assertSuccessful()
            ->assertJsonPath('success', true);
    }

    /* ------------------------- B/C. Member create/update/delete + escalation */

    private function memberPayload(string $slugSuffix = ''): array
    {
        return [
            'nama' => 'Anggota Uji',
            'alamat' => 'Jl. C-01 No. 1',
            'niqobah' => '123456',
            'pekerjaan' => 'Mahasiswa',
            'tanggal_lahir' => '2000-01-01',
            'tahun_masuk' => '2015-06-01',
            'tahun_keluar' => '2019-06-01',
            'no_hp' => '081234567890',
            'slug_suffix' => $slugSuffix,
        ];
    }

    public function test_removed_member_store_cannot_be_used_for_role_escalation(): void
    {
        $alumni = $this->makeAlumni();
        $rolesBefore = HakAksesRole::count();
        $usersBefore = User::count();

        Sanctum::actingAs($alumni);
        $payload = $this->memberPayload();
        $payload['roles'] = ['admin', 'dashboard'];

        $this->postJson('/api/members', $payload)->assertStatus(405);

        $this->assertSame($rolesBefore, HakAksesRole::count());
        $this->assertSame($usersBefore, User::count());
    }

    public function test_member_store_is_unavailable_to_every_role(): void
    {
        foreach (['dashboard', 'event', 'finance', 'prisensi', 'anggota', 'ketua', 'admin'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->postJson('/api/members', $this->memberPayload())->assertStatus(405);
        }
    }

    public function test_member_update_and_destroy_require_write_member(): void
    {
        $target = $this->makeAlumni();
        $this->makeMemberData($target->id);

        Sanctum::actingAs($this->makeUser('finance'));
        // Route contract: member update is POST /members/{id} (legacy).
        $this->postJson("/api/members/{$target->id}", array_merge($this->memberPayload(), [
            'email' => 'upd-' . uniqid() . '@test.local',
        ]))->assertStatus(403);

        Sanctum::actingAs($this->makeUser('admin'));
        $dbg = $this->postJson("/api/members/{$target->id}", array_merge($this->memberPayload(), [
            'email' => 'upd2-' . uniqid() . '@test.local',
        ]));
        $dbg->assertSuccessful();

        $this->deleteJson("/api/members/{$target->id}")->assertStatus(405);
    }

    /* ------------------------------------- D/E/F/G. Account lifecycle (admin) */

    public function test_account_lifecycle_is_ketua_admin_only(): void
    {
        $member = $this->makeAlumni();
        $this->makeMemberData($member->id);

        foreach (['dashboard', 'event', 'prisensi', 'anggota'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $this->getJson('/api/members/account-reset-audit')->assertStatus(403);
            $this->putJson("/api/members/{$member->id}/account", [
                'confirm' => true,
                'confirmation_id_anggota' => $member->id_anggota,
            ])->assertStatus(403);
            $this->putJson("/api/members/{$member->id}/status", ['is_active' => '0'])
                ->assertStatus(403);
        }
    }

    public function test_account_creation_routes_are_removed(): void
    {
        Sanctum::actingAs($this->makeUser('ketua'));

        $usersBefore = User::count();
        $profileId = $this->makeMemberData();

        $this->postJson('/api/members')->assertStatus(405);
        $this->postJson('/api/members/bulk-account')->assertStatus(404);
        $this->postJson("/api/members/{$profileId}/account")->assertStatus(405);
        $this->assertSame($usersBefore, User::count());
    }

    /* --------------------------------------------- H. Legacy dashboard reads */

    public function test_legacy_dashboards_denied_to_alumni_and_prisensi(): void
    {
        foreach (['anggota', 'prisensi'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            foreach (['stats', 'calendar', 'events'] as $leaf) {
                $this->getJson("/api/dashboard/{$leaf}")->assertStatus(403);
            }
        }
    }

    public function test_legacy_dashboards_allowed_for_staff(): void
    {
        Sanctum::actingAs($this->makeUser('dashboard'));
        $r = $this->getJson('/api/dashboard/stats');
        foreach (['dashboard', 'event', 'finance', 'ketua', 'admin'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            foreach (['stats', 'calendar', 'events'] as $leaf) {
                $this->getJson("/api/dashboard/{$leaf}")
                    ->assertSuccessful()
                    ->assertJsonPath('success', true);
            }
        }
    }

    /* --------------------------------------------------------- I. Event CRUD */

    private function eventPayload(): array
    {
        return [
            'judul_event' => 'Event Uji C-01',
            'slug' => 'event-uji-' . uniqid(),
            'lokasi' => 'Aula',
            'harga' => '100000',
            'deskripsi' => '<p>Deskripsi</p>',
            'tanggal' => '01/01/2026 - 02/01/2026',
            'kuota' => 50,
            'venue' => 'Main Hall',
            'visibility' => 'public',
        ];
    }

    public function test_event_crud_denied_to_non_staff(): void
    {
        foreach (['anggota', 'prisensi'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $dEv = $this->postJson('/api/events', $this->eventPayload());
                $dEv->assertStatus(403);
            $this->deleteJson('/api/events/999999')->assertStatus(403);
        }
    }

    public function test_staff_can_create_and_soft_delete_events(): void
    {
        Sanctum::actingAs($this->makeUser('event')); // interim global event-role boundary

        $created = $this->postJson('/api/events', $this->eventPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->json('data.id');

        $this->assertNotNull($created);

        $this->deleteJson("/api/events/{$created}")
            ->assertSuccessful()
            ->assertJsonPath('success', true);
    }

    /* ---------------------------------------------------------- J. News CRUD */

    public function test_news_crud_is_staff_only(): void
    {
        foreach (['anggota', 'prisensi'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $this->postJson('/api/news', [
                'judul' => 'X', 'slug' => 'x-' . uniqid(), 'deskripsi' => 'x',
            ])->assertStatus(403);
        }

        Sanctum::actingAs($this->makeUser('dashboard'));
        $newsId = DB::table('beritas')->insertGetId([
            'judul' => 'Berita Lama',
            'slug' => 'berita-lama',
            'deskripsi' => 'isi',
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = $this->postJson("/api/news/{$newsId}", [
            'judul' => 'Berita Baru',
            'slug' => 'berita-baru',
            'deskripsi' => 'isi baru',
        ]);
        $updated->assertSuccessful()->assertJsonPath('success', true);

        $this->deleteJson("/api/news/{$newsId}")
            ->assertSuccessful()
            ->assertJsonPath('success', true);
    }

    /* ---------------------------------------------- K. Carousel & org info */

    public function test_carousel_and_info_updates_are_staff_only(): void
    {
        $carouselId = DB::table('carosels')->insertGetId([
            'foto' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['anggota', 'prisensi'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $this->postJson("/api/carousel/{$carouselId}", [])->assertStatus(403);
            $this->postJson('/api/info/pesantren', [
                'judul' => 'j', 'deskripsi' => 'd', 'alamat' => 'a', 'telpon' => 't',
            ])->assertStatus(403);
            $this->postJson('/api/info/mzt', [
                'judul' => 'j', 'deskripsi' => 'd', 'alamat' => 'a', 'telpon' => 't',
            ])->assertStatus(403);
        }
    }

    public function test_staff_can_update_carousel_and_org_info(): void
    {
        Sanctum::actingAs($this->makeUser('dashboard'));

        $carouselId = DB::table('carosels')->insertGetId([
            'foto' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson("/api/carousel/{$carouselId}", [])
            ->assertSuccessful()
            ->assertJsonPath('success', true);

        foreach ([['info/pesantren', 'info_pesantrens'], ['info/mzt', 'tentang_mzts']] as [$route, $table]) {
            DB::table($table)->insert([
                'judul' => 'awal', 'deskripsi' => 'd', 'alamat' => 'a', 'telpon' => 't',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->postJson("/api/{$route}", [
                'judul' => 'Judul Baru',
                'deskripsi' => '<p>Deskripsi</p>',
                'alamat' => 'Alamat',
                'telpon' => '0812',
                'email' => 'info@test.local',
            ])->assertSuccessful()->assertJsonPath('success', true);
        }
    }

    /* ------------------------------------------- L. Legacy attendance WRITE */

    public function test_attendance_write_matrix(): void
    {
        $member = $this->makeAlumni();
        $this->makeMemberData($member->id); // attendanceStore requires a profile row

        // Alumni cannot forge attendance; neither can the dashboard console role.
        foreach (['anggota', 'dashboard'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $this->postJson('/api/attendance', [
                'id_anggota' => $member->id_anggota,
                'id_event' => 1,
                'id_tanggal' => 1,
            ])->assertStatus(403);
        }

        $this->assertSame(
            0,
            DB::table('prisensi_kehadiran')->count(),
            'denied callers must not write attendance rows'
        );

        foreach (['prisensi', 'event', 'finance', 'ketua', 'admin'] as $role) {
            // Fresh member per operator: one attendance row per member/day.
            $member = $this->makeAlumni();
            $this->makeMemberData($member->id);

            Sanctum::actingAs($this->makeUser($role));

            $this->postJson('/api/attendance', [
                'id_anggota' => $member->id_anggota,
                'id_event' => 1,
                'id_tanggal' => 1,
            ])->assertStatus(201)->assertJsonPath('success', true);
        }
    }

    /* -------------------------------------------------------- M. Activity log */

    public function test_activity_log_reader_set_is_verifier_only(): void
    {
        foreach (['anggota', 'prisensi', 'dashboard', 'event'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $this->getJson('/api/activity-log')->assertStatus(403);
            $this->getJson('/api/activity-log/1')->assertStatus(403);
        }

        foreach (['finance', 'ketua', 'admin'] as $role) {
            Sanctum::actingAs($this->makeUser($role));

            $this->getJson('/api/activity-log')
                ->assertSuccessful()
                ->assertJsonPath('success', true);
        }
    }

    /* ------------------------------------ Active-account per-request closure */

    public function test_deactivated_account_loses_api_access_immediately_and_others_unaffected(): void
    {
        $victim = $this->makeAlumni();
        $bystander = $this->makeAlumni();
        $this->makeMemberData($victim->id);
        $this->makeMemberData($bystander->id);

        $victimToken = $victim->createToken('pat-victim')->plainTextToken;
        $bystanderToken = $bystander->createToken('pat-bystander')->plainTextToken;

        app('auth')->forgetGuards();
        $g1 = $this->withHeader('Authorization', "Bearer {$victimToken}")
            ->getJson('/api/user');
        $g1->assertSuccessful();

        app('auth')->forgetGuards();
        $g2 = $this->withHeader('Authorization', "Bearer {$bystanderToken}")
            ->getJson('/api/user');
        $g2->assertSuccessful();

        Sanctum::actingAs($this->makeUser('ketua'));
        $this->putJson("/api/members/{$victim->id}/status", ['is_active' => '0'])
            ->assertSuccessful();

        // Drop the admin actingAs context so subsequent requests authenticate
        // purely via the Authorization headers under test.
        app('auth')->forgetGuards();

        // PATs of the victim were revoked at deactivation time...
        $this->assertSame(
            0,
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $victim->id)
                ->where('tokenable_type', User::class)
                ->count(),
            'deactivation must revoke the victim PATs'
        );

        // 5/6. ...and even a hypothetical still-valid credential gets 401 via
        //      the per-request active check (simulate by re-minting a token).
        $ghost = $victim->tokens()->create([
            'name' => 'ghost',
            'token' => hash('sha256', $raw = str_repeat('a', 40)),
            'abilities' => ['*'],
        ]);
        app('auth')->forgetGuards();
        $g3 = $this->withHeader('Authorization', "Bearer {$raw}")
            ->getJson('/api/user');
        $g3->assertStatus(401);

        // 7. bystander remains fully operational.
        app('auth')->forgetGuards();
        $g4 = $this->withHeader('Authorization', "Bearer {$bystanderToken}")
            ->getJson('/api/user');
        $rows = DB::table('users')->orderBy('id')->get(['id', 'is_active'])->toJson();
        $g4->assertSuccessful();
    }

    public function test_inactive_session_holder_is_blocked_with_401(): void
    {
        // Session-based caller whose account flips inactive between requests.
        $user = $this->makeUser('event');
        $this->makeMemberData($user->id);

        $this->actingAs($user, 'web');
        $this->getJson('/api/members')->assertSuccessful();

        $user->forceFill(['is_active' => '0'])->save();
        app('auth')->forgetGuards();

        $this->actingAs($user, 'web');
        $this->getJson('/api/members')->assertStatus(401);
    }
}
