<?php

namespace Tests\Feature;

use App\Models\HakAksesRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R2 — C-02 remaining content write-boundary coverage.
 *
 * Verifies every identified write path for info_pesantrens / tentang_mzts /
 * carosels stores only sanitized HTML (or, for image-path fields, the
 * framework-generated storage path and never user-controlled markup):
 *   - infoPesantrenUpdate via API (ApiController)
 *   - infoMztUpdate        via API (ApiController)
 *   - simpanDataPesantren2 via web admin (C_Tampilan)
 *   - simpanDataMzt        via web admin (C_Tampilan)
 *   - simpanCarosel        via web admin (C_Tampilan) — foto is a storage
 *     path field, not HTML; assert the framework-generated path is persisted.
 *
 * Semantic classification (driven by how home views render each field):
 *   - deskripsi / alamat / telpon: rendered with {!! !!} in home views
 *     (home_views.blade.php, tentang_mzt.blade.php) -> HTML sinks -> sanitize.
 *   - judul / email: rendered escaped or not at all -> plain text -> kept.
 *   - foto: image storage path -> never passed through the HTML sanitizer.
 *
 * Uses the manual-schema pattern (no RefreshDatabase because the legacy prod
 * migration chain depends on a prod-only `events.harga` column).
 */
class ContentTampilanWriteSanitizerTest extends TestCase
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
            'info_pesantrens',
            'tentang_mzts',
            'carosels',
            'users',
            'hak_akses_role',
            'data_users',
            'activitas_logs',
            'personal_access_tokens',
        ]);
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('activitas_logs');
        Schema::dropIfExists('data_users');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('carosels');
        Schema::dropIfExists('tentang_mzts');
        Schema::dropIfExists('info_pesantrens');
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

        Schema::create('info_pesantrens', function ($table) {
            $table->id();
            $table->string('judul');
            $table->text('deskripsi');
            $table->text('alamat');
            $table->text('foto');
            $table->string('telpon')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('tentang_mzts', function ($table) {
            $table->id();
            $table->string('judul');
            $table->text('deskripsi');
            $table->text('alamat');
            $table->text('foto');
            $table->string('telpon')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('carosels', function ($table) {
            $table->id();
            $table->string('judul')->nullable();
            $table->text('deskripsi')->nullable();
            $table->text('foto');
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

    private function maliciousAddress(): string
    {
        return '<script>alert(1)</script>Jl. Merdeka 88';
    }

    private function maliciousPhone(): string
    {
        return '<script>alert(1)</script>0812-3456-7890';
    }

    /* ------------------------------------------- API info_pesantrens path */

    public function test_api_info_pesantren_update_sanitizes_html_fields(): void
    {
        $user = $this->makeUser('dashboard', '0002000001');
        Sanctum::actingAs($user);

        DB::table('info_pesantrens')->insert([
            'judul' => 'Lama',
            'deskripsi' => '<p>lama</p>',
            'alamat' => 'Jl. Lama',
            'foto' => 'image/pesantren/lama.jpg',
            'telpon' => '021',
            'email' => 'lama@mzt.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/info/pesantren', [
            'judul' => '<b>Pesantren MZT</b>',
            'deskripsi' => $this->maliciousDescription(),
            'alamat' => $this->maliciousAddress(),
            'telpon' => $this->maliciousPhone(),
            'email' => 'info@mzt.test',
        ])->assertStatus(200);

        $row = DB::table('info_pesantrens')->first();

        $this->assertSame($this->expectedSanitizedDescription(), $row->deskripsi);
        $this->assertSame('<p>Jl. Merdeka 88</p>', $row->alamat);
        $this->assertSame('<p>0812-3456-7890</p>', $row->telpon);

        $this->assertSame('<b>Pesantren MZT</b>', $row->judul);
        $this->assertSame('info@mzt.test', $row->email);
        $this->assertSame('image/pesantren/lama.jpg', $row->foto);
    }

    public function test_api_info_mzt_update_sanitizes_html_fields(): void
    {
        $user = $this->makeUser('dashboard', '0002000002');
        Sanctum::actingAs($user);

        DB::table('tentang_mzts')->insert([
            'judul' => 'Lama',
            'deskripsi' => '<p>lama</p>',
            'alamat' => 'Jl. Lama',
            'foto' => 'image/mzt/lama.jpg',
            'telpon' => '021',
            'email' => 'lama@mzt.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/info/mzt', [
            'judul' => 'MZT 2026',
            'deskripsi' => $this->maliciousDescription(),
            'alamat' => $this->maliciousAddress(),
            'telpon' => $this->maliciousPhone(),
            'email' => 'info@mzt.test',
        ])->assertStatus(200);

        $row = DB::table('tentang_mzts')->first();

        $this->assertSame($this->expectedSanitizedDescription(), $row->deskripsi);
        $this->assertSame('<p>Jl. Merdeka 88</p>', $row->alamat);
        $this->assertSame('<p>0812-3456-7890</p>', $row->telpon);
        $this->assertSame('MZT 2026', $row->judul);
        $this->assertSame('info@mzt.test', $row->email);
        $this->assertSame('image/mzt/lama.jpg', $row->foto);
    }

    /* ------------------------------------------- web admin info_pesantrens */

    public function test_web_simpan_data_pesantren2_sanitizes_html_fields(): void
    {
        $user = $this->makeUser('tampilan', '0002000003');
        $this->actingAs($user);

        DB::table('info_pesantrens')->insert([
            'judul' => 'Lama',
            'deskripsi' => '<p>lama</p>',
            'alamat' => 'Jl. Lama',
            'foto' => 'image/pesantren/lama.jpg',
            'telpon' => '021',
            'email' => 'lama@mzt.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $_FILES['foto'] = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0];

        try {
            $this->post('/edit-info-pesantren/simpan', [
                'id' => 1,
                'judul' => '<b>Pesantren MZT</b>',
                'deskripsi' => $this->maliciousDescription(),
                'alamat' => $this->maliciousAddress(),
                'no_tlp' => $this->maliciousPhone(),
                'email' => 'info@mzt.test',
                'foto_lama' => 'image/pesantren/lama.jpg',
            ])->assertStatus(200);
        } finally {
            unset($_FILES['foto']);
        }

        $row = DB::table('info_pesantrens')->first();

        $this->assertSame($this->expectedSanitizedDescription(), $row->deskripsi);
        $this->assertSame('<p>Jl. Merdeka 88</p>', $row->alamat);
        $this->assertSame('<p>0812-3456-7890</p>', $row->telpon);
        $this->assertSame('<b>Pesantren MZT</b>', $row->judul);
        $this->assertSame('info@mzt.test', $row->email);
        $this->assertSame('image/pesantren/lama.jpg', $row->foto);
    }

    public function test_web_simpan_data_mzt_sanitizes_html_fields(): void
    {
        $user = $this->makeUser('tampilan', '0002000004');
        $this->actingAs($user);

        DB::table('tentang_mzts')->insert([
            'judul' => 'Lama',
            'deskripsi' => '<p>lama</p>',
            'alamat' => 'Jl. Lama',
            'foto' => 'image/mzt/lama.jpg',
            'telpon' => '021',
            'email' => 'lama@mzt.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $_FILES['foto'] = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0];

        try {
            $this->post('/edit-info-mzt/simpan', [
                'id' => 1,
                'judul' => 'MZT 2026',
                'deskripsi' => $this->maliciousDescription(),
                'alamat' => $this->maliciousAddress(),
                'no_tlp' => $this->maliciousPhone(),
                'email' => 'info@mzt.test',
                'foto_lama' => 'image/mzt/lama.jpg',
            ])->assertStatus(200);
        } finally {
            unset($_FILES['foto']);
        }

        $row = DB::table('tentang_mzts')->first();

        $this->assertSame($this->expectedSanitizedDescription(), $row->deskripsi);
        $this->assertSame('<p>Jl. Merdeka 88</p>', $row->alamat);
        $this->assertSame('<p>0812-3456-7890</p>', $row->telpon);
        $this->assertSame('MZT 2026', $row->judul);
        $this->assertSame('info@mzt.test', $row->email);
        $this->assertSame('image/mzt/lama.jpg', $row->foto);
    }

    /* ------------------------------------------------ web admin carosels  */

    public function test_web_simpan_carosel_preserves_framework_image_path(): void
    {
        Storage::fake('public');

        $oldPath = 'image/carosel/old-test.jpg';
        File::makeDirectory(dirname(public_path('storage/'.$oldPath)), 0755, true, true);
        File::put(public_path('storage/'.$oldPath), 'old-content');

        try {
            DB::table('carosels')->insert([
                'judul' => null,
                'deskripsi' => null,
                'foto' => $oldPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = $this->makeUser('tampilan', '0002000005');
            $this->actingAs($user);

            $_FILES['foto'] = ['name' => 'baru.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => 0, 'size' => 1];

            $this->post('/edit-carosel/simpan', [
                'id' => 1,
                'foto_lama' => $oldPath,
                'foto' => UploadedFile::fake()->image('baru.jpg'),
            ])->assertStatus(200);

            $stored = DB::table('carosels')->where('id', 1)->value('foto');

            $this->assertStringStartsWith('image/carosel/', $stored);
            $this->assertTrue(Storage::disk('public')->exists($stored));
            $this->assertFalse(File::exists(public_path('storage/'.$oldPath)));
        } finally {
            unset($_FILES['foto']);
            File::deleteDirectory(public_path('storage/image/carosel'));
        }
    }
}
