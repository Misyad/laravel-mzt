<?php

namespace Tests\Feature;

use App\Models\HakAksesRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R1 — C-02 stored XSS write-boundary coverage.
 *
 * Verifies every identified description write path stores only sanitized HTML:
 *   - events  store/update via API (ApiController::eventsStore/eventsUpdate)
 *   - news    store/update via API (ApiController::newsStore/newsUpdate)
 *   - beritas store via web admin (C_Berita::storeData)
 *
 * Uses the DashboardTest manual-schema pattern (no RefreshDatabase because the
 * legacy prod migration chain depends on a prod-only `events.harga` column).
 */
class ContentWriteSanitizerTest extends TestCase
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

        $this->truncate(['events', 'beritas', 'users', 'hak_akses_role', 'data_users', 'activitas_logs', 'personal_access_tokens']);
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('activitas_logs');
        Schema::dropIfExists('data_users');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('beritas');
        Schema::dropIfExists('events');
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

        Schema::create('data_users', function ($table) {
            $table->id();
            $table->integer('id_users');
            $table->string('no_hp')->nullable();
            $table->text('barcode')->nullable();
            $table->text('alamat');
            $table->string('pekerjaan');
            $table->string('niqobah');
            $table->date('tanggal_lahir');
            $table->date('tahun_masuk');
            $table->date('tahun_keluar');
            $table->text('foto');
            $table->enum('is_active', ['1', '0'])->default('1');
            $table->timestamps();
        });

        Schema::create('activitas_logs', function ($table) {
            $table->id();
            $table->string('subject');
            $table->string('url');
            $table->string('method');
            $table->string('agent')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function ($table) {
            $table->id();
            $table->string('judul_event');
            $table->text('deskripsi');
            $table->string('tanggal');
            $table->string('lokasi');
            $table->date('tanggal_selesai');
            $table->date('tanggal_mulai');
            $table->string('slug')->nullable();
            $table->text('banner')->nullable();
            $table->enum('is_active', ['1', '0'])->default('1');
            $table->string('harga')->nullable();
            $table->unsignedInteger('kuota')->nullable();
            $table->string('venue')->nullable();
            $table->string('visibility', 20)->default('public');
            $table->dateTime('registrasi_dibuka')->nullable();
            $table->dateTime('registrasi_ditutup')->nullable();
            $table->decimal('harga_amount', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('beritas', function ($table) {
            $table->id();
            $table->string('judul');
            $table->text('deskripsi');
            $table->text('foto')->nullable();
            $table->string('slug')->nullable();
            $table->string('create_at')->default('');
            $table->string('edit_at')->nullable();
            $table->enum('is_active', ['1', '0'])->default('1');
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

    private function makeUser(string $role, string $idAnggota): User
    {
        $user = User::factory()->create(['id_anggota' => $idAnggota]);
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
        ]);

        return $user;
    }

    private function maliciousDescription(): string
    {
        return '<p>Halo</p><script>alert(1)</script>'
            .'<img src="javascript:alert(2)" onerror="alert(3)">'
            .'<a href="javascript:alert(4)">link</a>'
            .'<p onclick="alert(5)">aman</p>';
    }

    private function expectedSanitizedDescription(): string
    {
        return '<p>Halo</p>'
            .'<img>'
            .'<a>link</a>'
            .'<p>aman</p>';
    }

    /* --------------------------------------------------- events write path */

    public function test_events_store_sanitizes_description(): void
    {
        $user = $this->makeUser('event', '0001000001');
        Sanctum::actingAs($user);

        $this->postJson('/api/events', [
            'judul_event' => 'Event Aman',
            'slug' => 'event-aman-'.uniqid(),
            'lokasi' => 'Aula',
            'harga' => '50000',
            'deskripsi' => $this->maliciousDescription(),
            'tanggal' => '01/01/2026 - 02/01/2026',
        ])->assertStatus(201);

        $this->assertSame(
            $this->expectedSanitizedDescription(),
            DB::table('events')->value('deskripsi')
        );
    }

    public function test_events_update_sanitizes_description(): void
    {
        $user = $this->makeUser('event', '0001000002');
        Sanctum::actingAs($user);

        $id = DB::table('events')->insertGetId([
            'judul_event' => 'Event Lama',
            'deskripsi' => '<p>lama</p>',
            'tanggal' => '01/01/2026 - 02/01/2026',
            'lokasi' => 'Aula',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-02',
            'slug' => 'event-lama-'.uniqid(),
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/events/'.$id, [
            'judul_event' => 'Event Baru',
            'slug' => 'event-baru-'.uniqid(),
            'lokasi' => 'Gedung',
            'harga' => '75000',
            'deskripsi' => $this->maliciousDescription(),
            'tanggal' => '01/01/2026 - 02/01/2026',
        ])->assertStatus(200);

        $this->assertSame(
            $this->expectedSanitizedDescription(),
            DB::table('events')->where('id', $id)->value('deskripsi')
        );
    }

    /* ------------------------------------------------------ news write path */

    public function test_news_store_sanitizes_description(): void
    {
        $user = $this->makeUser('dashboard', '0001000003');
        Sanctum::actingAs($user);

        $this->postJson('/api/news', [
            'judul' => 'Berita Aman',
            'slug' => 'berita-aman-'.uniqid(),
            'deskripsi' => $this->maliciousDescription(),
        ])->assertStatus(201);

        $this->assertSame(
            $this->expectedSanitizedDescription(),
            DB::table('beritas')->value('deskripsi')
        );
    }

    public function test_news_update_sanitizes_description(): void
    {
        $user = $this->makeUser('dashboard', '0001000004');
        Sanctum::actingAs($user);

        $id = DB::table('beritas')->insertGetId([
            'judul' => 'Berita Lama',
            'deskripsi' => '<p>lama</p>',
            'foto' => '',
            'slug' => 'berita-lama-'.uniqid(),
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/news/'.$id, [
            'judul' => 'Berita Baru',
            'slug' => 'berita-baru-'.uniqid(),
            'deskripsi' => $this->maliciousDescription(),
        ])->assertStatus(200);

        $this->assertSame(
            $this->expectedSanitizedDescription(),
            DB::table('beritas')->where('id', $id)->value('deskripsi')
        );
    }

    /* -------------------------------------------------- beritas write path */

    public function test_beritas_web_admin_store_sanitizes_description(): void
    {
        Storage::fake('public');
        $user = $this->makeUser('berita', '0001000005');
        $this->actingAs($user);

        $this->post('/tabel-berita/store', [
            'judul' => 'Berita Admin',
            'slug' => 'berita-admin-'.uniqid(),
            'deskripsi' => $this->maliciousDescription(),
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertStatus(200);

        $this->assertSame(
            $this->expectedSanitizedDescription(),
            DB::table('beritas')->value('deskripsi')
        );
    }
}
