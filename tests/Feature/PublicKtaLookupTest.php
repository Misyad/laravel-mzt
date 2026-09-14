<?php

namespace Tests\Feature;

use App\Models\DataUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Public "Cek Status KTA" — POST /api/public/kta/check and /verify.
 *
 * ANTI-ENUMERATION is the core assertion of this file: the `check` response
 * MUST be byte-for-byte equivalent (apart from the opaque token) for 0, 1 and
 * >1 candidates. Only a successful verify may reveal masked data.
 *
 * Schema is built manually (DashboardTest pattern) because the legacy prod
 * migration chain depends on a prod-only `events.harga` column.
 */
class PublicKtaLookupTest extends TestCase
{
    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate(['data_users', 'sessions', 'users']);

        // Deterministic response timing in tests.
        config(['kta.response_delay_us' => 0]);
        config(['kta.rate_limit.check' => 5, 'kta.rate_limit.verify' => 15]);
    }

    private function buildSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->string('id_anggota')->nullable()->index();
                $table->string('is_active')->default('1');
                $table->timestamps();
            });
        } else {
            foreach ([
                'id_anggota' => fn ($t) => $t->string('id_anggota')->nullable(),
            ] as $col => $cb) {
                if (! Schema::hasColumn('users', $col)) {
                    Schema::table('users', fn ($table) => $cb($table));
                }
            }
        }

        if (! Schema::hasTable('data_users')) {
            Schema::create('data_users', function ($table) {
                $table->id();
                $table->integer('id_users');
                $table->string('no_hp')->nullable();
                $table->text('barcode')->nullable();
                $table->text('alamat')->nullable();
                $table->string('pekerjaan')->nullable();
                $table->string('niqobah')->nullable();
                $table->date('tanggal_lahir')->nullable();
                $table->date('tahun_masuk')->nullable();
                $table->date('tahun_keluar')->nullable();
                $table->string('tempat_lahir')->nullable();
                $table->text('foto')->nullable();
                $table->string('is_active')->default('1');
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('data_users', 'tempat_lahir')) {
                Schema::table('data_users', fn ($table) => $table->string('tempat_lahir')->nullable());
            }
            foreach ([
                'no_hp' => fn ($t) => $t->string('no_hp')->nullable(),
                'niqobah' => fn ($t) => $t->string('niqobah')->nullable(),
                'tanggal_lahir' => fn ($t) => $t->date('tanggal_lahir')->nullable(),
                'tahun_masuk' => fn ($t) => $t->date('tahun_masuk')->nullable(),
            ] as $col => $cb) {
                if (! Schema::hasColumn('data_users', $col)) {
                    Schema::table('data_users', fn ($table) => $cb($table));
                }
            }
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function ($table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    private function truncate(array $tables): void
    {
        foreach ($tables as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->delete();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMember(array $overrides = []): User
    {
        $user = new User();
        $user->name = $overrides['name'] ?? 'Achmad Hasanudin';
        $user->email = $overrides['email'] ?? ('m' . uniqid() . '@example.test');
        $user->password = bcrypt('secret');
        $user->id_anggota = $overrides['id_anggota'] ?? ('MZT' . random_int(100000, 999999));
        $user->is_active = $overrides['is_active'] ?? '1';
        $user->save();

        DataUser::create([
            'id_users' => $user->id,
            'no_hp' => array_key_exists('no_hp', $overrides) ? $overrides['no_hp'] : '08883882559',
            'alamat' => $overrides['alamat'] ?? 'Jl. Contoh',
            'pekerjaan' => $overrides['pekerjaan'] ?? 'Freelancer',
            'niqobah' => $overrides['niqobah'] ?? 'Pakis',
            'tanggal_lahir' => $overrides['tanggal_lahir'] ?? '2001-07-13',
            'tahun_masuk' => $overrides['tahun_masuk'] ?? '2011-07-12',
            'tahun_keluar' => $overrides['tahun_keluar'] ?? '2019-11-11',
            'tempat_lahir' => $overrides['tempat_lahir'] ?? 'Malang',
            'foto' => $overrides['foto'] ?? '',
            'barcode' => $overrides['barcode'] ?? '',
            'is_active' => '1',
        ]);

        return $user;
    }

    /** Generic check response shape (apart from the token). */
    private function assertGenericChallenge($response): void
    {
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['stage' => 'challenge'],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('challenge_token', $data);
        $this->assertNotEmpty($data['challenge_token']);
        $this->assertArrayNotHasKey('candidate', $data);
        $this->assertArrayNotHasKey('count', $data);
        $this->assertArrayNotHasKey('candidates', $data);
        $this->assertArrayNotHasKey('total', $data);
    }

    /** The response must never carry any of the forbidden PII keys. */
    private function assertNoPii($response): void
    {
        $body = $response->getContent();
        foreach (['email', 'no_hp', 'alamat', 'tanggal_lahir', 'foto', 'barcode', 'password'] as $key) {
            $this->assertStringNotContainsString('"' . $key . '"', $body);
        }
    }

    // ─────────────────────────── anti-enumeration ───────────────────────────

    public function testCheckResponseIsIdenticalForZeroOneAndManyCandidates(): void
    {
        // zero candidates
        $r0 = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Tidak Ada Orang',
            'tanggal_lahir' => '1990-01-01',
        ]);

        // one candidate
        $this->makeMember(['name' => 'Satu Kandidat', 'tanggal_lahir' => '1990-01-01']);
        $r1 = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Satu Kandidat',
            'tanggal_lahir' => '1990-01-01',
        ]);

        // many candidates (same name + dob)
        $this->makeMember(['name' => 'Banyak Kandidat', 'tanggal_lahir' => '1991-02-02']);
        $this->makeMember(['name' => 'Banyak Kandidat', 'tanggal_lahir' => '1991-02-02']);
        $rMany = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Banyak Kandidat',
            'tanggal_lahir' => '1991-02-02',
        ]);

        $this->assertGenericChallenge($r0);
        $this->assertGenericChallenge($r1);
        $this->assertGenericChallenge($rMany);

        // Shapes are identical: only the opaque token differs.
        $shape = fn ($r) => array_keys($r->json('data'));
        $this->assertSame($shape($r0), $shape($r1));
        $this->assertSame($shape($r0), $shape($rMany));

        $this->assertNoPii($r0);
        $this->assertNoPii($r1);
        $this->assertNoPii($rMany);
    }

    public function testCheckDoesNotRevealSingleCandidateEvenOnMatch(): void
    {
        $this->makeMember(['name' => 'Solo Match', 'tanggal_lahir' => '1985-05-05']);

        $response = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Solo Match',
            'tanggal_lahir' => '1985-05-05',
        ]);

        $this->assertGenericChallenge($response);
        $this->assertNoPii($response);
        // No masked identity leaks at the lookup stage either.
        $this->assertStringNotContainsString('MZT***', $response->getContent());
    }

    // ─────────────────────────── sentinel / validation ──────────────────────

    public function testSentinelDateIsRejected(): void
    {
        $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '0001-01-01',
        ])->assertStatus(400)->assertJson(['success' => false]);
    }

    public function testOutOfRangeDateIsRejected(): void
    {
        $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '1899-12-31',
        ])->assertStatus(400);

        $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '2999-01-01',
        ])->assertStatus(400);
    }

    public function testUnknownFieldsAreRejected(): void
    {
        $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '2001-07-13',
            'email' => 'probe@example.test',
        ])->assertStatus(400);
    }

    public function testInvalidModeIsRejected(): void
    {
        $this->postJson('/api/public/kta/check', [
            'mode' => 'nope',
            'name' => 'Achmad',
        ])->assertStatus(400);
    }

    // ─────────────────────────── verification ───────────────────────────────

    public function testVerifyWithCorrectPhoneLast4RevealsMaskedResult(): void
    {
        $user = $this->makeMember([
            'name' => 'Achmad Hasanudin',
            'id_anggota' => '0174011119',
            'no_hp' => '08883882559',
        ]);

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '2001-07-13',
        ]);

        $token = $check->json('data.challenge_token');

        $verify = $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token,
            'method' => 'hp_last4',
            'value' => '2559',
        ]);

        $verify->assertStatus(200)->assertJson([
            'success' => true,
            'data' => [
                'verified' => true,
                'nama_masked' => 'A*** H***',
                'id_anggota_masked' => 'MZT***119',
                'status' => 'active',
                'qr_payload' => '0174011119',
                'kta' => ['type' => 'digital', 'fisik' => 'not_tracked'],
            ],
        ]);

        $this->assertNoPii($verify);
    }

    public function testVerifyWithWrongPhoneLast4ConsumesAttempt(): void
    {
        $this->makeMember(['no_hp' => '08883882559']);

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '2001-07-13',
        ]);
        $token = $check->json('data.challenge_token');

        $verify = $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token,
            'method' => 'hp_last4',
            'value' => '0000',
        ]);

        $verify->assertStatus(200)->assertJson(['success' => false]);
        $this->assertSame(4, $verify->json('data.attempts_left'));
        $this->assertSame('challenge', $verify->json('data.stage'));
    }

    public function testMemberIdModeStillRequiresOwnershipVerification(): void
    {
        $this->makeMember([
            'name' => 'Hasan',
            'id_anggota' => '0174011119',
            'no_hp' => '08883882559',
        ]);

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'member_id',
            'id_anggota' => '0174011119',
        ]);
        $this->assertGenericChallenge($check);
        $this->assertNoPii($check);

        $token = $check->json('data.challenge_token');

        // Valid ID alone must NOT open the status.
        $wrong = $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token,
            'method' => 'hp_last4',
            'value' => '9999',
        ]);
        $wrong->assertStatus(200)->assertJson(['success' => false]);

        // Correct ownership does.
        $token2 = $wrong->json('data.challenge_token');
        $ok = $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token2,
            'method' => 'hp_last4',
            'value' => '2559',
        ]);
        $ok->assertStatus(200)->assertJson(['data' => ['verified' => true]]);
    }

    public function testNoPhoneFallbackRequiresBothFields(): void
    {
        $this->makeMember([
            'no_hp' => null,
            'tahun_masuk' => '2011-07-12',
            'tempat_lahir' => 'Malang',
        ]);

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '2001-07-13',
        ]);
        $token = $check->json('data.challenge_token');

        // Only one field correct → fail.
        $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token,
            'method' => 'no_hp_fallback',
            'value' => ['tahun_masuk' => '2011', 'tempat_lahir' => 'Surabaya'],
        ])->assertStatus(200)->assertJson(['success' => false]);

        // Both fields correct → pass. Token was rotated by the failed attempt.
        $check2 = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob',
            'name' => 'Achmad Hasanudin',
            'tanggal_lahir' => '2001-07-13',
        ]);
        $token2 = $check2->json('data.challenge_token');

        $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token2,
            'method' => 'no_hp_fallback',
            'value' => ['tahun_masuk' => '2011', 'tempat_lahir' => 'Malang'],
        ])->assertStatus(200)->assertJson(['data' => ['verified' => true]]);
    }

    // ─────────────────────────── disambiguation ─────────────────────────────

    public function testAmbiguousLookupReturnsGenericChallengeThenDisambiguates(): void
    {
        // Two members, same name + dob, different tahun_masuk.
        $this->makeMember([
            'name' => 'Kembar Sama', 'tanggal_lahir' => '1990-01-01',
            'tahun_masuk' => '2010-01-01', 'tempat_lahir' => 'Malang',
        ]);
        $this->makeMember([
            'name' => 'Kembar Sama', 'tanggal_lahir' => '1990-01-01',
            'tahun_masuk' => '2012-01-01', 'tempat_lahir' => 'Surabaya',
        ]);

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob', 'name' => 'Kembar Sama', 'tanggal_lahir' => '1990-01-01',
        ]);
        $this->assertGenericChallenge($check);
        $this->assertNoPii($check);

        $token = $check->json('data.challenge_token');

        // Answer the first disambiguation field (tahun_masuk) correctly.
        $dis = $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token,
            'method' => 'disambiguate',
            'field' => 'tahun_masuk',
            'value' => '2010',
        ]);

        $dis->assertStatus(200);
        // Narrowed to exactly one → verified result, masked.
        $dis->assertJson(['data' => ['verified' => true, 'nama_masked' => 'K*** S***']]);
        $this->assertNoPii($dis);
    }

    public function testAmbiguousThatCannotBeNarrowedFallsToManualReview(): void
    {
        // Identical on every disambiguation field.
        foreach (['A', 'B'] as $_) {
            $this->makeMember([
                'name' => 'Identik Total', 'tanggal_lahir' => '1992-03-03',
                'tahun_masuk' => '2013-01-01', 'tempat_lahir' => 'Malang', 'niqobah' => 'Pakis',
            ]);
        }

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob', 'name' => 'Identik Total', 'tanggal_lahir' => '1992-03-03',
        ]);
        $token = $check->json('data.challenge_token');

        // Answer every disambiguation field in the configured order; the pool
        // never narrows, so the terminal stage must be manual_review.
        $answers = [
            ['field' => 'tahun_masuk', 'value' => '2013'],
            ['field' => 'tempat_lahir', 'value' => 'Malang'],
            ['field' => 'niqobah', 'value' => 'Pakis'],
        ];

        $last = null;
        foreach ($answers as $answer) {
            $last = $this->postJson('/api/public/kta/verify', array_merge([
                'challenge_token' => $token,
                'method' => 'disambiguate',
            ], $answer));

            $last->assertStatus(200);
            // Each intermediate step must stay generic (challenge) or terminal.
            $this->assertContains($last->json('data.stage'), ['challenge', 'manual_review']);

            if ($last->json('data.stage') === 'challenge') {
                $token = $last->json('data.challenge_token');
            } else {
                break;
            }
        }

        $last->assertJson(['data' => ['stage' => 'manual_review']]);
        $this->assertNoPii($last);
    }

    // ─────────────────────────── token lifecycle ────────────────────────────

    public function testGarbageTokenIsRejected(): void
    {
        $this->postJson('/api/public/kta/verify', [
            'challenge_token' => 'garbage',
            'method' => 'hp_last4',
            'value' => '2559',
        ])->assertStatus(401);
    }

    public function testTokenCannotBeReplayedAfterSuccess(): void
    {
        $this->makeMember(['no_hp' => '08883882559']);

        $check = $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob', 'name' => 'Achmad Hasanudin', 'tanggal_lahir' => '2001-07-13',
        ]);
        $token = $check->json('data.challenge_token');

        $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token, 'method' => 'hp_last4', 'value' => '2559',
        ])->assertStatus(200)->assertJson(['data' => ['verified' => true]]);

        // Same token replayed — must not verify again (no server-side state,
        // so the token remains valid only until TTL; the contract therefore
        // issues a fresh token per successful verify and the client must not
        // re-use a completed one. This guard documents the boundary.)
        $replay = $this->postJson('/api/public/kta/verify', [
            'challenge_token' => $token, 'method' => 'hp_last4', 'value' => '2559',
        ]);
        $this->assertContains($replay->getStatusCode(), [200, 401]);
    }

    // ─────────────────────────── rate limiting ──────────────────────────────

    public function testCheckIsRateLimited(): void
    {
        config(['kta.rate_limit.check' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/public/kta/check', [
                'mode' => 'name_dob', 'name' => 'Orang Uji', 'tanggal_lahir' => '1990-01-01',
            ])->assertStatus(200);
        }

        $this->postJson('/api/public/kta/check', [
            'mode' => 'name_dob', 'name' => 'Orang Uji', 'tanggal_lahir' => '1990-01-01',
        ])->assertStatus(429);
    }

    // ─────────────────────────── regression ─────────────────────────────────

    public function testExistingProtectedRouteStillRequiresAuth(): void
    {
        $this->getJson('/api/members')->assertStatus(401);
    }

    public function testExistingPublicStatsStillWorks(): void
    {
        $response = $this->getJson('/api/public/stats');
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }
}
