<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\HakAksesRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R3 — C-02 hybrid authentication (first-party browser session vs. stateless PAT).
 *
 * `POST /api/login` must behave differently depending on how the request is
 * made:
 *   - First-party browser request (Sanctum stateful: request carries a referer
 *     / origin that matches `config('sanctum.stateful')`) -> establish a web
 *     session (HttpOnly cookie), return `{ success, user }` WITHOUT a token,
 *     and create NO personal access token.
 *   - Stateless API client (no stateful origin) -> issue a personal access
 *     token as before, with no session.
 *
 * `POST /api/logout` must revoke the presented PAT (stateless clients) AND/OR
 * destroy the web session (browser clients). Revocation is scoped to the
 * presented token only — other tokens of the same user stay valid (I-2 locked:
 * no mass PAT revocation).
 *
 * CSRF: the stateful pipeline runs the configured VerifyCsrfToken, so browser
 * state-changing calls must carry a valid XSRF token. Default phpunit feature
 * tests skip CSRF verification (`VerifyCsrfToken::runningUnitTests()` returns
 * true), so the two CSRF tests below swap in StrictVerifyCsrfToken which forces
 * verification on.
 *
 * TEST-INFRA NOTE: guards are cached in the AuthManager for the whole test and
 * RequestGuard::setRequest() does NOT clear its cached user, so without a
 * reset the sanctum guard would keep authenticating a user whose PAT/session
 * was just revoked (a feature-test artifact — each real HTTP request is a
 * separate process). Every request below therefore starts with
 * `app('auth')->forgetGuards()`.
 *
 * Schema is built manually (DatabaseTransactions pattern) because the legacy
 * prod migration chain depends on a prod-only `events.harga` column, making a
 * full RefreshDatabase impossible in this repo.
 */
class AuthHybridTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $schemaBuilt = false;

    /** Captured response cookies (name => value), replayed like a browser jar. */
    private array $cookieJar = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate([
            'personal_access_tokens',
            'hak_akses_role',
            'data_users',
            'users',
        ]);
        $this->seedActiveRoleCatalog(['anggota', 'admin']);

        $this->cookieJar = [];
    }

    private function buildSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('data_users');
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
            $table->timestamp('last_login')->nullable();
            $table->unsignedInteger('login_count')->default(0);
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
    }

    private function truncate(array $tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function makeUser(string $role, string $idAnggota, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'id_anggota' => $idAnggota,
            'password_changed_at' => now(),
        ], $overrides));
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
        ]);

        DB::table('data_users')->insert([
            'id_users' => $user->id,
            'no_hp' => '0812-3456-7890',
            'barcode' => 'MZT00001',
            'alamat' => 'Jl. Merdeka 88',
            'pekerjaan' => 'Pelajar',
            'niqobah' => 'MZT 01',
            'tanggal_lahir' => '2000-01-01',
            'tahun_masuk' => '2020-07-01',
            'tahun_keluar' => '2023-06-30',
            'foto' => 'image/anggota/user.jpg',
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /* ---------------------------------------------------------- test helpers */

    /**
     * Each simulated HTTP request must re-resolve the auth guards. Guards are
     * cached in the AuthManager for the whole test and RequestGuard does not
     * invalidate its cached user when the request is swapped, so without this
     * reset a revoked PAT/session would keep authenticating later requests.
     */
    private function resetAuth(): void
    {
        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
    }

    private function sessionCookieName(): string
    {
        return config('session.cookie');
    }

    /** Capture every Set-Cookie of the response into the simulated jar. */
    private function captureCookies($response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookieJar[$cookie->getName()] = $cookie->getValue() ?? '';
        }
    }

    /**
     * Replay the captured cookies onto the next request. Session + XSRF cookies
     * are encrypted by EncryptCookies, so they must be forwarded unencrypted
     * (the test client would otherwise encrypt them a second time).
     */
    private function withBrowserState()
    {
        $this->resetAuth();

        $cookies = [];
        if (isset($this->cookieJar[$this->sessionCookieName()])) {
            $cookies[$this->sessionCookieName()] = $this->cookieJar[$this->sessionCookieName()];
        }

        return $this->withHeaders(['Origin' => 'http://localhost:8080'])
            ->withUnencryptedCookies($cookies);
    }

    /**
     * Bootstrap the browser session: GET /sanctum/csrf-cookie sets the session
     * and XSRF-TOKEN cookies. Called with Origin so the login request that
     * follows is treated as stateful.
     */
    private function bootstrapBrowserSession(): void
    {
        $this->resetAuth();

        $response = $this->withHeaders(['Origin' => 'http://localhost:8080'])
            ->get('/sanctum/csrf-cookie');
        $response->assertStatus(204);
        $this->captureCookies($response);
    }

    private function xsrfHeaderValue(): string
    {
        return $this->cookieJar['XSRF-TOKEN'] ?? '';
    }

    /* ---------------------------------------------------------- stateless PAT */

    public function test_stateless_login_returns_pat_and_creates_no_session(): void
    {
        $this->makeUser('anggota', '0002000001');

        $this->resetAuth();
        $response = $this->postJson('/api/login', [
            'id_anggota' => '0002000001',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id_anggota', '0002000001')
            ->assertJsonStructure(['token']);

        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        // Stateless request must not create a session cookie.
        $this->assertStringNotContainsString(
            $this->sessionCookieName(),
            (string) $response->headers->get('set-cookie'),
        );
    }

    public function test_identifier_login_accepts_normalized_email(): void
    {
        $user = $this->makeUser('anggota', '0002000012', [
            'email' => 'member.login@example.test',
        ]);

        $this->resetAuth();
        $this->postJson('/api/login', [
            'identifier' => '  MEMBER.LOGIN@EXAMPLE.TEST  ',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.id_anggota', '0002000012');
    }

    public function test_identifier_login_preserves_leading_zero_member_id(): void
    {
        $user = $this->makeUser('anggota', '0000000013');

        $this->resetAuth();
        $this->postJson('/api/login', [
            'identifier' => '0000000013',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.id_anggota', '0000000013');
    }

    public function test_legacy_id_anggota_payload_remains_supported(): void
    {
        $user = $this->makeUser('anggota', '0002000014');

        $this->resetAuth();
        $this->postJson('/api/login', [
            'id_anggota' => '0002000014',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_invalid_unknown_and_inactive_credentials_share_the_generic_response(): void
    {
        $this->makeUser('anggota', '0002000002');
        $this->makeUser('anggota', '0002000003', ['is_active' => '0']);

        $this->resetAuth();
        $wrongPassword = $this->postJson('/api/login', [
            'identifier' => '0002000002',
            'password' => 'wrong-password',
        ]);
        $this->resetAuth();
        $unknownUser = $this->postJson('/api/login', [
            'identifier' => 'unknown-member',
            'password' => 'wrong-password',
        ]);
        $this->resetAuth();
        $inactiveUser = $this->postJson('/api/login', [
            'identifier' => '0002000003',
            'password' => 'password',
        ]);

        foreach ([$wrongPassword, $unknownUser, $inactiveUser] as $response) {
            $response->assertStatus(422)
                ->assertJsonPath('errors.identifier.0', 'Email, nomor anggota, atau password salah.');
        }
        $this->assertSame($wrongPassword->json(), $unknownUser->json());
        $this->assertSame($wrongPassword->json(), $inactiveUser->json());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_ambiguous_identifier_is_rejected_with_the_generic_response(): void
    {
        $this->makeUser('anggota', 'collision@example.test', [
            'email' => 'first@example.test',
        ]);
        $this->makeUser('anggota', '0002000015', [
            'email' => 'collision@example.test',
        ]);

        $this->resetAuth();
        $this->postJson('/api/login', [
            'identifier' => 'collision@example.test',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.identifier.0', 'Email, nomor anggota, atau password salah.');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_login_rejects_payloads_with_both_identifier_fields(): void
    {
        $this->makeUser('anggota', '0002000016');

        $this->resetAuth();
        $this->postJson('/api/login', [
            'identifier' => '0002000016',
            'id_anggota' => '0002000016',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'id_anggota']);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_member_login_is_rate_limited_by_identifier_and_ip(): void
    {
        $identifier = 'rate-limit-'.bin2hex(random_bytes(8));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->resetAuth();
            $this->postJson('/api/login', [
                'identifier' => $identifier,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->resetAuth();
        $this->postJson('/api/login', [
            'identifier' => $identifier,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_pat_authenticates_user_endpoint_and_is_audited(): void
    {
        $user = $this->makeUser('anggota', '0002000004');

        $this->resetAuth();
        $login = $this->postJson('/api/login', [
            'id_anggota' => '0002000004',
            'password' => 'password',
        ]);
        $token = $login->json('token');

        $this->resetAuth();
        $this->getJson('/api/user', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('user.id', $user->id);

        $fresh = $user->fresh();
        $this->assertSame(1, $fresh->login_count);
        $this->assertNotNull($fresh->last_login);
    }

    /* ------------------------------------------------------ stateful session */

    public function test_stateful_login_creates_session_without_pat(): void
    {
        $user = $this->makeUser('anggota', '0002000005');
        $this->bootstrapBrowserSession();

        $login = $this->withBrowserState()
            ->withHeaders(['X-XSRF-TOKEN' => $this->xsrfHeaderValue()])
            ->postJson('/api/login', [
                'id_anggota' => '0002000005',
                'password' => 'password',
            ]);

        $login->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('token');

        // A first-party browser login must NOT mint a personal access token.
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        // Session cookie was issued and the session was regenerated.
        $this->captureCookies($login);
        $this->assertArrayHasKey($this->sessionCookieName(), $this->cookieJar);
        $this->assertArrayHasKey('XSRF-TOKEN', $this->cookieJar);
    }

    public function test_stateful_session_authenticates_protected_endpoint(): void
    {
        $user = $this->makeUser('anggota', '0002000006');
        $this->bootstrapBrowserSession();

        $login = $this->withBrowserState()
            ->withHeaders(['X-XSRF-TOKEN' => $this->xsrfHeaderValue()])
            ->postJson('/api/login', [
                'id_anggota' => '0002000006',
                'password' => 'password',
            ]);
        $login->assertStatus(200);
        $this->captureCookies($login);

        // The browser session (session cookie only, no Authorization header)
        // must authenticate auth:sanctum endpoints.
        $this->withBrowserState()
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.must_change_password', false);
    }

    public function test_stateful_logout_destroys_the_session(): void
    {
        $user = $this->makeUser('anggota', '0002000007');
        $this->bootstrapBrowserSession();

        $login = $this->withBrowserState()
            ->withHeaders(['X-XSRF-TOKEN' => $this->xsrfHeaderValue()])
            ->postJson('/api/login', [
                'id_anggota' => '0002000007',
                'password' => 'password',
            ]);
        $login->assertStatus(200);
        $this->captureCookies($login);

        $logout = $this->withBrowserState()
            ->withHeaders(['X-XSRF-TOKEN' => $this->xsrfHeaderValue()])
            ->postJson('/api/logout');
        $logout->assertStatus(200)->assertJsonPath('success', true);

        // A browser follows the logout response's new (empty) session cookie,
        // so re-send the fresh session cookie: it must no longer authenticate.
        $this->captureCookies($logout);
        $this->withBrowserState()
            ->getJson('/api/user')
            ->assertStatus(401);

        // Session logout must not mint or leave PATs behind either.
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertNotNull($user->fresh()); // user row untouched
    }

    /* ------------------------------------------------------------ revocation */

    public function test_stateless_logout_revokes_only_the_presented_pat(): void
    {
        $user = $this->makeUser('anggota', '0002000008');

        $this->resetAuth();
        $tokenA = $user->createToken('api-token')->plainTextToken;
        $tokenB = $user->createToken('api-token')->plainTextToken;
        $this->assertSame(2, DB::table('personal_access_tokens')->count());

        $this->resetAuth();
        $this->withToken($tokenA)->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        // Token B (I-2: no mass revocation) stays valid.
        $this->resetAuth();
        $this->withToken($tokenB)->getJson('/api/user')->assertStatus(200);

        // Revoked token A no longer authenticates.
        $this->resetAuth();
        $this->withToken($tokenA)->getJson('/api/user')->assertStatus(401);
    }

    public function test_browser_session_and_pat_coexist_without_interference(): void
    {
        $user = $this->makeUser('anggota', '0002000009');

        // Stateless client logs in first and keeps its PAT.
        $this->resetAuth();
        $login = $this->postJson('/api/login', [
            'id_anggota' => '0002000009',
            'password' => 'password',
        ]);
        $token = $login->json('token');
        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        // The same user then signs in from a browser (session-based).
        $this->bootstrapBrowserSession();
        $sessionLogin = $this->withBrowserState()
            ->withHeaders(['X-XSRF-TOKEN' => $this->xsrfHeaderValue()])
            ->postJson('/api/login', [
                'id_anggota' => '0002000009',
                'password' => 'password',
            ]);
        $sessionLogin->assertStatus(200)->assertJsonMissingPath('token');
        $this->captureCookies($sessionLogin);

        // The pre-existing PAT remains valid — creating a session must not
        // revoke issued tokens.
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->resetAuth();
        $this->withToken($token)->getJson('/api/user')->assertStatus(200);

        // And the browser session works independently of the PAT.
        $this->withBrowserState()->getJson('/api/user')->assertStatus(200);
    }

    /* ------------------------------------------------------------------ CSRF */

    public function test_stateful_login_without_csrf_token_is_rejected(): void
    {
        $this->enableStrictCsrf();
        $this->makeUser('anggota', '0002000010');
        $this->bootstrapBrowserSession();

        // Session cookie present but X-XSRF-TOKEN header missing.
        $this->withBrowserState()
            ->postJson('/api/login', [
                'id_anggota' => '0002000010',
                'password' => 'password',
            ])
            ->assertStatus(419);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_stateful_login_with_valid_csrf_token_succeeds(): void
    {
        $this->enableStrictCsrf();
        $user = $this->makeUser('anggota', '0002000011');
        $this->bootstrapBrowserSession();

        $login = $this->withBrowserState()
            ->withHeaders(['X-XSRF-TOKEN' => $this->xsrfHeaderValue()])
            ->postJson('/api/login', [
                'id_anggota' => '0002000011',
                'password' => 'password',
            ]);

        $login->assertStatus(200)->assertJsonPath('user.id', $user->id);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    /* ------------------------------------------------------------------ misc */

    public function test_unauthorised_user_endpoint_returns_401(): void
    {
        $this->resetAuth();
        $this->getJson('/api/user')->assertStatus(401);
    }

    private function enableStrictCsrf(): void
    {
        config(['sanctum.middleware.verify_csrf_token' => StrictVerifyCsrfToken::class]);
    }
}

/**
 * Forces CSRF verification inside feature tests. The framework's default
 * `runningUnitTests()` returns true under phpunit, which would otherwise skip
 * the token check and make the CSRF tests meaningless.
 */
class StrictVerifyCsrfToken extends VerifyCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
