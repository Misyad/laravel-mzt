<?php

namespace Tests\Feature;

use App\Http\Controllers\admin\C_Anggota;
use App\Http\Controllers\ApiController;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\HakAksesRole;
use App\Models\RoleUser;
use App\Models\User;
use App\Support\RoleGuard;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate([
            'activitas_logs',
            'sessions',
            'personal_access_tokens',
            'hak_akses_role',
            'role_user',
            'data_users',
            'users',
        ]);

        foreach (['anggota', 'profil', 'dashboard', 'event', 'finance', 'ketua', 'admin'] as $role) {
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
        Schema::dropIfExists('activitas_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('hak_akses_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('data_users');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
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

        Schema::create('hak_akses_role', function ($table) {
            $table->id();
            $table->integer('id_users');
            $table->string('nama_role');
            $table->string('hak_akses')->default('access');
            $table->timestamps();
        });

        Schema::create('role_user', function ($table) {
            $table->id();
            $table->string('nama_role');
            $table->string('is_active')->default('1');
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

        Schema::create('activitas_logs', function ($table) {
            $table->id();
            $table->string('subject');
            $table->string('url');
            $table->string('method');
            $table->string('agent')->nullable();
            $table->string('user_id')->nullable();
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

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'id_anggota' => (string) random_int(1000000, 9999999),
            'is_active' => '1',
            'password_changed_at' => now(),
        ], $attributes));

        $this->addRole($user, $role);

        return $user;
    }

    private function makeEligibleMember(array $attributes = []): User
    {
        $user = $this->makeUser(' anggota ', $attributes);
        $this->addRole($user, ' PROFIL ');
        $this->makeProfile($user);

        return $user;
    }

    private function makeProfile(User $user, array $attributes = []): int
    {
        return DB::table('data_users')->insertGetId(array_merge([
            'id_users' => $user->id,
            'alamat' => 'Jl. Test',
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function addRole(User $user, string $role, string $access = 'access'): void
    {
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => $access,
        ]);
    }

    private function makeSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }

    public function test_effective_roles_are_normalized_and_deny_wins_in_auth_payloads(): void
    {
        $user = $this->makeUser(' Anggota ');
        $this->addRole($user, ' ADMIN ');
        $this->addRole($user, 'admin', 'no_accesss');

        $this->postJson('/api/login', [
            'id_anggota' => $user->id_anggota,
            'password' => 'password',
        ])->assertSuccessful()
            ->assertJsonPath('user.roles', ['anggota']);
        $this->assertSame(1, $user->fresh()->login_count);
        $this->assertNotNull($user->fresh()->last_login);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertSuccessful()
            ->assertJsonPath('user.roles', ['anggota']);

        $this->getJson('/api/me')
            ->assertSuccessful()
            ->assertJsonPath('user.roles', ['anggota']);
    }

    public function test_active_role_catalog_fails_closed_when_empty_missing_or_unavailable(): void
    {
        $user = $this->makeUser('admin');

        RoleUser::query()->delete();
        $this->assertSame([], RoleGuard::roles($user));

        RoleUser::create(['nama_role' => 'anggota', 'is_active' => '1']);
        $this->assertSame([], RoleGuard::roles($user));

        $resolver = RoleUser::getConnectionResolver();
        RoleUser::setConnectionResolver(new class implements ConnectionResolverInterface
        {
            public function connection($name = null)
            {
                throw new \RuntimeException('catalog unavailable');
            }

            public function getDefaultConnection()
            {
                return 'unavailable';
            }

            public function setDefaultConnection($name) {}
        });

        try {
            $this->assertSame([], RoleGuard::roleState([
                (object) ['nama_role' => 'admin', 'hak_akses' => 'access'],
            ])['effective']);
        } finally {
            RoleUser::setConnectionResolver($resolver);
        }
    }

    public function test_active_role_catalog_fails_closed_on_duplicate_and_conflicting_rows(): void
    {
        $user = $this->makeUser('admin');

        RoleUser::create(['nama_role' => ' ADMIN ', 'is_active' => '1']);
        $this->assertSame([], RoleGuard::roles($user));
        $this->assertFalse(RoleGuard::isAdmin($user));

        RoleUser::whereIn('nama_role', ['admin', ' ADMIN '])->delete();
        RoleUser::create(['nama_role' => 'Admin', 'is_active' => '1']);
        RoleUser::create(['nama_role' => ' admin ', 'is_active' => '0']);
        $this->assertSame([], RoleGuard::roles($user));
        $this->assertFalse(RoleGuard::isAdmin($user));
    }

    public function test_reset_audit_reports_strict_eligibility_and_role_exclusions(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeProfile($admin);
        $eligible = $this->makeEligibleMember();
        $staffMember = $this->makeEligibleMember();
        $this->addRole($staffMember, 'finance');
        $conflicted = $this->makeEligibleMember();
        $this->addRole($conflicted, 'anggota', 'no_accesss');
        $duplicateProfile = $this->makeEligibleMember();
        $this->makeProfile($duplicateProfile);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/members/account-reset-audit')
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_accounts', 5)
            ->assertJsonPath('data.eligible_count', 1)
            ->assertJsonPath('data.ineligible_count', 4);

        $items = collect($response->json('data.items'))->keyBy('id_users');
        $this->assertTrue($items[$eligible->id]['eligible']);
        $this->assertSame('ROLE_NOT_ALLOWED', $items[$staffMember->id]['reason_code']);
        $this->assertSame('ROLE_ACCESS_CONFLICT', $items[$conflicted->id]['reason_code']);
        $this->assertSame('PROFILE_COUNT_INVALID', $items[$duplicateProfile->id]['reason_code']);
        $this->assertArrayNotHasKey('password', $items[$eligible->id]);
    }

    public function test_per_account_reset_preserves_identity_and_status_and_revokes_access(): void
    {
        $admin = $this->makeUser('ketua');
        $target = $this->makeEligibleMember([
            'password' => Hash::make('old-password'),
            'remember_token' => 'old-token',
            'password_changed_at' => now(),
        ]);
        $profile = DB::table('data_users')->where('id_users', $target->id)->first();
        $target->createToken('one');
        $target->createToken('two');
        $this->makeSession($target, 'target-session-one');
        $this->makeSession($target, 'target-session-two');

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/members/{$target->id}/account", [
            'confirm' => true,
            'confirmation_id_anggota' => $target->id_anggota,
        ])->assertSuccessful()
            ->assertJsonPath('data.temporary_password', 'mzt1234')
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonMissingPath('password');

        $fresh = $target->fresh();
        $this->assertTrue(Hash::check('mzt1234', $fresh->password));
        $this->assertNull($fresh->password_changed_at);
        $this->assertSame('1', (string) $fresh->is_active);
        $this->assertSame($target->id_anggota, $fresh->id_anggota);
        $this->assertNotSame('old-token', $fresh->remember_token);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame($profile->id, DB::table('data_users')->where('id_users', $target->id)->value('id'));
        $this->assertSame('1', (string) DB::table('data_users')->where('id_users', $target->id)->value('is_active'));

        $log = DB::table('activitas_logs')->latest('id')->first();
        $this->assertStringNotContainsString('mzt1234', $log->subject);
        $this->assertStringNotContainsString($fresh->password, $log->subject);
    }

    public function test_reset_never_creates_missing_account_and_rejects_unsafe_targets(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeEligibleMember(['password' => Hash::make('unchanged-password')]);
        $this->addRole($target, 'regional');
        $usersBefore = User::count();
        $originalHash = $target->password;

        Sanctum::actingAs($admin);

        $this->putJson('/api/members/999999/account', [
            'confirm' => true,
            'confirmation_id_anggota' => '999999',
        ])->assertStatus(404);
        $this->assertSame($usersBefore, User::count());

        $this->putJson("/api/members/{$target->id}/account", [
            'confirm' => true,
            'confirmation_id_anggota' => $target->id_anggota,
        ])->assertStatus(409)
            ->assertJsonPath('code', 'ROLE_NOT_ALLOWED');
        $this->assertSame($originalHash, $target->fresh()->password);
    }

    public function test_status_changes_only_strict_existing_member_accounts(): void
    {
        $admin = $this->makeUser('admin');
        $member = $this->makeEligibleMember();
        $staff = $this->makeEligibleMember();
        $this->addRole($staff, 'finance');
        $administrator = $this->makeUser('ketua');
        $this->makeProfile($administrator);

        Sanctum::actingAs($admin);

        $this->putJson("/api/members/{$member->id}/status", ['is_active' => '0'])
            ->assertSuccessful();
        $this->assertSame('0', (string) $member->fresh()->is_active);
        $this->assertSame('0', (string) DB::table('data_users')->where('id_users', $member->id)->value('is_active'));

        foreach ([$staff, $administrator] as $ineligible) {
            $this->putJson("/api/members/{$ineligible->id}/status", ['is_active' => '0'])
                ->assertStatus(409)
                ->assertJsonPath('code', 'ROLE_NOT_ALLOWED');
            $this->assertSame('1', (string) $ineligible->fresh()->is_active);
        }

        $this->putJson('/api/members/999999/status', ['is_active' => '0'])->assertStatus(404);

        $invalidStatus = $this->makeEligibleMember(['is_active' => 'legacy']);
        $this->putJson("/api/members/{$invalidStatus->id}/status", ['is_active' => '0'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_STATUS_INVALID');

        $mismatched = $this->makeEligibleMember(['is_active' => '0']);
        $this->putJson("/api/members/{$mismatched->id}/status", ['is_active' => '1'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_STATUS_MISMATCH');

        DB::table('data_users')->where('id_users', $mismatched->id)->update(['is_active' => '0']);
        $this->putJson("/api/members/{$mismatched->id}/status", ['is_active' => '1'])
            ->assertSuccessful();
    }

    public function test_deactivation_revokes_all_credentials_and_rotates_remember_token(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeEligibleMember(['remember_token' => 'old-remember-token']);
        $bystander = $this->makeEligibleMember();
        $target->createToken('one');
        $target->createToken('two');
        $bystander->createToken('other');
        $this->makeSession($target, 'target-session-one');
        $this->makeSession($target, 'target-session-two');
        $this->makeSession($bystander, 'bystander-session');

        Sanctum::actingAs($admin);

        $this->putJson("/api/members/{$target->id}/status", ['is_active' => '0'])
            ->assertSuccessful();

        $fresh = $target->fresh();
        $this->assertSame('0', (string) $fresh->is_active);
        $this->assertSame('0', (string) DB::table('data_users')->where('id_users', $target->id)->value('is_active'));
        $this->assertNotSame('old-remember-token', $fresh->remember_token);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, $bystander->tokens()->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $bystander->id)->count());

        $rotatedRememberToken = $fresh->remember_token;
        $this->putJson("/api/members/{$target->id}/status", ['is_active' => '1'])
            ->assertSuccessful();
        $this->assertSame($rotatedRememberToken, $target->fresh()->remember_token);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_deactivation_fails_closed_without_database_sessions_but_activation_remains_available(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeEligibleMember(['remember_token' => 'unchanged-remember-token']);
        $target->createToken('one');
        $this->makeSession($target, 'target-session');
        $inactive = $this->makeEligibleMember(['is_active' => '0']);
        DB::table('data_users')->where('id_users', $inactive->id)->update(['is_active' => '0']);

        Sanctum::actingAs($admin);
        config(['session.driver' => 'array']);

        $this->putJson("/api/members/{$target->id}/status", ['is_active' => '0'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'SESSION_REVOCATION_UNAVAILABLE');

        $fresh = $target->fresh();
        $this->assertSame('1', (string) $fresh->is_active);
        $this->assertSame('1', (string) DB::table('data_users')->where('id_users', $target->id)->value('is_active'));
        $this->assertSame('unchanged-remember-token', $fresh->remember_token);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $target->id)->count());

        $this->putJson("/api/members/{$inactive->id}/status", ['is_active' => '1'])
            ->assertSuccessful();
        $this->assertSame('1', (string) $inactive->fresh()->is_active);
        $this->assertSame('1', (string) DB::table('data_users')->where('id_users', $inactive->id)->value('is_active'));
    }

    public function test_members_directory_includes_only_strict_synchronized_active_and_inactive_members(): void
    {
        $admin = $this->makeUser('admin');
        $active = $this->makeEligibleMember();
        $inactive = $this->makeEligibleMember();
        $staff = $this->makeEligibleMember();
        $this->addRole($staff, 'finance');
        $duplicate = $this->makeEligibleMember();
        $this->makeProfile($duplicate);
        $mismatched = $this->makeEligibleMember(['is_active' => '0']);
        $orphanProfileId = DB::table('data_users')->insertGetId([
            'id_users' => 999999,
            'alamat' => 'Jl. Orphan',
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/members/{$inactive->id}/status", ['is_active' => '0'])
            ->assertSuccessful();

        $response = $this->getJson('/api/members')
            ->assertSuccessful()
            ->assertJsonPath('success', true);

        $members = collect($response->json('data'))->keyBy('id_users');
        $this->assertCount(2, $members);
        $this->assertSame('1', (string) $members[$active->id]['account_is_active']);
        $this->assertSame('0', (string) $members[$inactive->id]['account_is_active']);
        $this->assertSame([
            'id',
            'id_users',
            'id_anggota',
            'nama',
            'email',
            'no_hp',
            'alamat',
            'niqobah',
            'pekerjaan',
            'foto',
            'tahun_masuk',
            'tahun_keluar',
            'tempat_lahir',
            'tanggal_lahir',
            'has_account',
            'account_is_active',
            'login_count',
            'last_login',
        ], array_keys($members[$inactive->id]));
        $this->assertFalse($members->has($staff->id));
        $this->assertFalse($members->has($duplicate->id));
        $this->assertFalse($members->has($mismatched->id));
        $this->assertFalse(collect($response->json('data'))->contains('id', $orphanProfileId));
    }

    public function test_reset_requires_exact_confirmation_and_database_sessions_before_mutation(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeEligibleMember(['password' => Hash::make('unchanged-password')]);
        $originalHash = $target->password;

        Sanctum::actingAs($admin);

        $this->putJson("/api/members/{$target->id}/account", [
            'confirm' => false,
            'confirmation_id_anggota' => $target->id_anggota,
        ])->assertStatus(422);

        $this->putJson("/api/members/{$target->id}/account", [
            'confirm' => true,
            'confirmation_id_anggota' => 'wrong',
        ])->assertStatus(422);

        config(['session.driver' => 'array']);
        $this->putJson("/api/members/{$target->id}/account", [
            'confirm' => true,
            'confirmation_id_anggota' => $target->id_anggota,
        ])->assertStatus(503)
            ->assertJsonPath('code', 'SESSION_REVOCATION_UNAVAILABLE');

        $this->assertSame($originalHash, $target->fresh()->password);
        $this->assertNotNull($target->fresh()->password_changed_at);
    }

    public function test_account_creation_and_bulk_routes_are_unavailable(): void
    {
        $admin = $this->makeUser('admin');
        Sanctum::actingAs($admin);
        $before = User::count();

        $this->postJson('/api/members')->assertStatus(405);
        $this->postJson('/api/members/bulk-account')->assertStatus(404);
        $this->postJson('/api/members/999999/account')->assertStatus(405);
        $this->postJson('/tabel-anggota/store')->assertStatus(404);

        $request = Request::create('/api/members', 'POST');
        $request->setUserResolver(fn () => $admin);
        $this->assertSame(405, (new ApiController)->membersStore($request)->getStatusCode());
        $this->assertSame(405, (new C_Anggota)->storeData($request)->getStatusCode());

        $this->assertSame($before, User::count());
    }

    public function test_profile_and_member_edits_cannot_change_password_hashes(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeEligibleMember(['password' => Hash::make('OriginalPassword1')]);
        $originalHash = $target->password;
        $payload = [
            'nama' => 'Nama Baru',
            'alamat' => 'Jl. Baru',
            'niqobah' => 'Niqobah',
            'pekerjaan' => 'Pekerja',
            'tanggal_lahir' => '2000-01-01',
            'tahun_masuk' => '2015-01-01',
            'tahun_keluar' => '2019-01-01',
            'no_hp' => '08123456789',
            'password' => 'InjectedPassword1',
            'password_confirmation' => 'InjectedPassword1',
        ];

        Sanctum::actingAs($admin);
        $this->postJson("/api/members/{$target->id}", $payload)->assertStatus(422);

        Sanctum::actingAs($target);
        $this->putJson('/api/profile', [
            'alamat' => 'Jl. Baru',
            'password' => 'InjectedPassword1',
            'password_confirmation' => 'InjectedPassword1',
        ])->assertStatus(422);
        $this->postJson('/api/profile', $payload)->assertStatus(422);

        $this->actingAs($target, 'web');
        $this->post('/profil/edit', $payload)->assertSessionHasErrors('password');

        $this->actingAs($admin, 'web');
        $this->post('/tabel-anggota/edit', array_merge($payload, [
            'id_users' => $target->id,
        ]))->assertSessionHasErrors('password');

        $this->assertSame($originalHash, $target->fresh()->password);
    }

    public function test_must_change_password_can_only_use_the_auth_allowlist(): void
    {
        $user = $this->makeUser('anggota', ['password_changed_at' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')->assertSuccessful();
        $this->getJson('/api/me')->assertSuccessful();
        $this->getJson('/api/profile')->assertStatus(428)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        config(['session.driver' => 'array']);
        $this->putJson('/api/password', [
            'current_password' => 'password',
            'password' => 'DifferentPassword1',
            'password_confirmation' => 'DifferentPassword1',
        ])->assertStatus(503);
        $this->postJson('/api/logout')->assertSuccessful();
    }

    public function test_admin_with_null_password_timestamp_is_also_forced(): void
    {
        $admin = $this->makeUser('admin', ['password_changed_at' => null]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/members/account-reset-audit')->assertStatus(428)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_legacy_web_routes_cannot_bypass_forced_password_change(): void
    {
        $user = $this->makeUser('profil', ['password_changed_at' => null]);
        $this->makeProfile($user);

        $this->actingAs($user, 'web');
        $this->get('/profil')->assertStatus(428);
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_password_changed_middleware_fails_closed_when_attribute_is_missing(): void
    {
        $user = new User;
        $user->setRawAttributes(['id' => 123], true);
        $request = Request::create('/api/profile', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsurePasswordChanged)->handle($request, fn () => response('allowed'));

        $this->assertSame(428, $response->getStatusCode());
    }

    public function test_browser_password_change_preserves_only_the_current_session(): void
    {
        $user = $this->makeUser('anggota', [
            'password' => Hash::make('CurrentPassword1'),
            'password_changed_at' => null,
        ]);
        $this->makeSession($user, 'other-browser-session');

        $this->withSession(['password_hash_web' => $user->getAuthPassword()]);
        $this->actingAs($user, 'web');

        $this->withHeader('Origin', 'http://localhost:8080')->putJson('/api/password', [
            'current_password' => 'CurrentPassword1',
            'password' => 'NewSecurePassword1',
            'password_confirmation' => 'NewSecurePassword1',
        ])->assertSuccessful();

        $fresh = $user->fresh();
        $currentSessionId = session()->getId();
        $this->assertSame($fresh->password, session('password_hash_web'));
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(DB::table('sessions')->where('id', $currentSessionId)->exists());
    }

    public function test_password_change_validates_password_and_pat_change_revokes_every_credential(): void
    {
        $user = $this->makeUser('anggota', [
            'password' => Hash::make('CurrentPassword1'),
            'password_changed_at' => null,
            'remember_token' => 'old-token',
        ]);
        $user->createToken('one');
        $user->createToken('two');
        $this->makeSession($user, 'session-one');
        $this->makeSession($user, 'session-two');

        Sanctum::actingAs($user);

        $this->putJson('/api/password', [
            'current_password' => 'CurrentPassword1',
            'password' => 'mzt1234',
            'password_confirmation' => 'mzt1234',
        ])->assertStatus(422);

        $this->putJson('/api/password', [
            'current_password' => 'CurrentPassword1',
            'password' => 'CurrentPassword1',
            'password_confirmation' => 'CurrentPassword1',
        ])->assertStatus(422);

        $this->putJson('/api/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePassword1',
            'password_confirmation' => 'NewSecurePassword1',
        ])->assertStatus(422);

        $this->putJson('/api/password', [
            'current_password' => 'CurrentPassword1',
            'password' => 'NewSecurePassword1',
            'password_confirmation' => 'NewSecurePassword1',
        ])->assertSuccessful()
            ->assertJsonPath('data.must_change_password', false);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewSecurePassword1', $fresh->password));
        $this->assertNotNull($fresh->password_changed_at);
        $this->assertNotSame('old-token', $fresh->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
