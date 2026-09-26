<?php

namespace Tests\Feature;

use App\Mail\MemberApplicationApprovedMail;
use App\Mail\PasswordResetTokenMail;
use App\Mail\VerificationCodeMail;
use App\Models\ApplicantSession;
use App\Models\HakAksesRole;
use App\Models\MemberApplication;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberOnboardingTest extends TestCase
{
    private static bool $schemaBuilt = false;

    private array $cookieJar = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }

        $this->truncate([
            'member_role_logs',
            'member_application_logs',
            'member_id_counters',
            'applicant_sessions',
            'member_application_email_verifications',
            'member_applications',
            'member_submission_locks',
            'member_email_locks',
            'password_reset_requests',
            'account_setup_email_verifications',
            'account_activation_logs',
            'account_activation_challenges',
            'sessions',
            'personal_access_tokens',
            'hak_akses_role',
            'role_user',
            'data_users',
            'users',
        ]);

        foreach (['anggota', 'profil', 'ketua', 'admin'] as $role) {
            RoleUser::create(['nama_role' => $role, 'is_active' => '1']);
        }

        config([
            'member_onboarding.activation_enabled' => true,
            'member_onboarding.applications_enabled' => true,
            'member_onboarding.activation.response_delay_us' => 0,
            'member_onboarding.frontend_url' => 'https://members.example.test',
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
            'filesystems.disks.public.throw' => true,
        ]);

        Mail::fake();
        Storage::fake('public');
        $this->cookieJar = [];
    }

    public function test_disabled_member_applications_return_a_stable_error_code(): void
    {
        config(['member_onboarding.applications_enabled' => false]);

        foreach ([
            $this->postJson('/api/public/member-applications'),
            $this->postJson('/api/applicant/login'),
            $this->getJson('/api/applicant/me'),
        ] as $response) {
            $response->assertStatus(503)->assertJson([
                'success' => false,
                'code' => 'MEMBER_APPLICATIONS_DISABLED',
                'message' => 'Layanan tidak tersedia.',
            ]);
        }
    }

    public function test_inactive_legacy_member_can_claim_once_and_must_complete_setup(): void
    {
        $member = $this->makeMember('Legacy Member', '1990-01-02', 'Bandung', '2010-07-01', [
            'is_active' => '0',
            'email' => null,
            'email_verified_at' => null,
            'account_claimed_at' => null,
        ]);
        DB::table('data_users')->where('id_users', $member->id)->update(['is_active' => '0']);
        $staff = $this->makeMember('Staff Member', '1988-03-04', 'Jakarta', '2008-07-01');
        $this->addRole($staff, 'admin');

        $eligibleCheck = $this->withHeader('User-Agent', 'claim-browser')->postJson('/api/public/account-activation/check', [
            'name' => ' Legacy  Member ',
            'tanggal_lahir' => '1990-01-02',
        ])->assertSuccessful()->assertJsonPath('success', true);

        $staffCheck = $this->withHeader('User-Agent', 'claim-browser')->postJson('/api/public/account-activation/check', [
            'name' => 'Staff Member',
            'tanggal_lahir' => '1988-03-04',
        ])->assertSuccessful();

        $missingCheck = $this->withHeader('User-Agent', 'claim-browser')->postJson('/api/public/account-activation/check', [
            'name' => 'Missing Member',
            'tanggal_lahir' => '1988-03-04',
        ])->assertSuccessful();

        $this->assertSame(array_keys($staffCheck->json()), array_keys($missingCheck->json()));
        $this->assertSame(array_keys($staffCheck->json('data')), array_keys($missingCheck->json('data')));

        $this->withHeader('User-Agent', 'claim-browser')->postJson('/api/public/account-activation/verify', [
            'challenge_token' => $staffCheck->json('data.challenge_token'),
            'tempat_lahir' => 'Jakarta',
            'tahun_masuk' => '2008',
        ])->assertSuccessful()->assertJsonPath('success', false)->assertJsonMissingPath('data.id_anggota');

        $verify = $this->withHeader('User-Agent', 'claim-browser')->postJson('/api/public/account-activation/verify', [
            'challenge_token' => $eligibleCheck->json('data.challenge_token'),
            'tempat_lahir' => ' bandung ',
            'tahun_masuk' => '2010',
        ])->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_anggota', $member->id_anggota);

        $fresh = $member->fresh();
        $this->assertSame('1', (string) $fresh->is_active);
        $this->assertSame('1', (string) DB::table('data_users')->where('id_users', $member->id)->value('is_active'));
        $this->assertTrue(Hash::check('mzt12345', $fresh->password));
        $this->assertTrue($fresh->account_setup_required);
        $this->assertNull($fresh->account_claimed_at);

        $this->withHeader('User-Agent', 'claim-browser')->postJson('/api/public/account-activation/verify', [
            'challenge_token' => $eligibleCheck->json('data.challenge_token'),
            'tempat_lahir' => 'Bandung',
            'tahun_masuk' => '2010',
        ])->assertStatus(401);

        app('auth')->forgetGuards();
        $login = $this->postJson('/api/login', [
            'id_anggota' => $member->id_anggota,
            'password' => 'mzt12345',
        ])->assertSuccessful()
            ->assertJsonPath('user.account_setup_required', true);
        $token = $login->json('token');

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/profile')
            ->assertStatus(428)
            ->assertJsonPath('code', 'ACCOUNT_SETUP_REQUIRED');

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/account/setup/email', [
            'email' => ' New.Email@Example.COM ',
        ])->assertSuccessful();

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;
            $this->assertNull($mail->applicationNumber);
            $this->assertStringContainsString($mail->code, $mail->htmlContent());

            return true;
        });

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/account/setup/email/verify', ['code' => $code])
            ->assertSuccessful();

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/account/setup/complete', [
            'current_password' => 'mzt12345',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])->assertSuccessful();

        $fresh = $member->fresh();
        $this->assertSame('new.email@example.com', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNotNull($fresh->password_changed_at);
        $this->assertNotNull($fresh->account_claimed_at);
        $this->assertFalse($fresh->account_setup_required);
        $this->assertTrue(Hash::check('StrongPassword1!', $fresh->password));
        $this->assertSame(0, $fresh->tokens()->count());

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertStatus(401);
        app('auth')->forgetGuards();
        $this->postJson('/api/login', [
            'id_anggota' => $member->id_anggota,
            'password' => 'StrongPassword1!',
        ])->assertSuccessful();
    }

    public function test_verified_email_password_reset_is_generic_single_use_and_revokes_access(): void
    {
        $user = $this->makeMember('Reset Member', '1992-01-01', 'Bogor', '2011-01-01', [
            'email' => 'reset@example.com',
            'email_verified_at' => now(),
            'account_claimed_at' => now(),
            'password' => Hash::make('OldPassword1!'),
        ]);
        $user->createToken('existing');
        $this->makeSession($user, 'reset-session');

        $known = $this->postJson('/api/public/password/forgot', ['email' => ' RESET@example.com '])->assertSuccessful();
        $unknown = $this->postJson('/api/public/password/forgot', ['email' => 'missing@example.com'])->assertSuccessful();
        $this->assertSame($known->json('message'), $unknown->json('message'));

        $token = null;
        Mail::assertSent(PasswordResetTokenMail::class, function (PasswordResetTokenMail $mail) use (&$token) {
            $token = $mail->token;
            $this->assertStringContainsString(
                'https://members.example.test/reset-password?'.http_build_query([
                    'token' => $mail->token,
                    'email' => 'reset@example.com',
                ]),
                html_entity_decode($mail->render())
            );

            return true;
        });

        $payload = [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword2!',
            'password_confirmation' => 'NewPassword2!',
        ];
        $this->postJson('/api/public/password/reset', $payload)->assertSuccessful();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewPassword2!', $fresh->password));
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $fresh->id)->count());
        $this->postJson('/api/public/password/reset', $payload)->assertStatus(422);
    }

    public function test_rejected_application_can_be_revised_and_resubmitted(): void
    {
        $application = $this->makeSubmittedApplication('rejected@example.com');
        $admin = $this->makeUser('admin', ['account_claimed_at' => now()]);
        Sanctum::actingAs($admin);
        $this->putJson("/api/member-applications/{$application->uuid}/reject", [
            'reason' => 'Data perlu diperbaiki.',
        ])->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::REJECTED);

        $sessionMarker = Str::random(64);
        $this->withSession([
            'applicant_application_id' => $application->id,
            'applicant_session_id' => $sessionMarker,
        ]);
        ApplicantSession::create([
            'session_id' => $sessionMarker,
            'member_application_id' => $application->id,
            'created_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->putJson('/api/applicant/application', [
            'alamat' => 'Jl. Data Diperbaiki',
            'tahun_masuk' => '2011',
            'tahun_keluar' => '2017',
        ])->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::SUBMITTED)
            ->assertJsonPath('data.application.tahun_masuk', '2011')
            ->assertJsonPath('data.application.tahun_keluar', '2017')
            ->assertJsonPath('data.application.rejection_reason', null);

        $fresh = $application->fresh();
        $this->assertSame('rejected@example.com', $fresh->active_email);
        $this->assertNull($fresh->reviewed_by);
        $this->assertNull($fresh->rejected_at);
    }

    public function test_application_lifecycle_rejects_role_injection_and_approval_allocates_fixed_ids(): void
    {
        $this->bootstrapBrowserSession();
        $payload = $this->applicationPayload('applicant@example.com');
        $submissionToken = $payload['submission_token'];
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('password_confirmation', $payload);
        $payload['roles'] = ['admin'];
        $this->browser()->post('/api/public/member-applications', $payload, ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertSame(0, MemberApplication::count());
        $this->assertSame(0, User::count());

        unset($payload['roles']);
        $create = $this->browser()->post('/api/public/member-applications', $payload, ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.application.status', MemberApplication::PENDING_EMAIL)
            ->assertJsonPath('data.application.tahun_masuk', '2012')
            ->assertJsonPath('data.application.tahun_keluar', '2018');
        $this->captureCookies($create);
        $application = MemberApplication::firstOrFail();
        $this->assertSame('APP-'.strtoupper(str_replace('-', '', $application->uuid)), $application->application_number);
        $verificationMail = Mail::sent(VerificationCodeMail::class)->last();
        $this->assertSame($application->application_number, $verificationMail->applicationNumber);
        $this->assertStringContainsString($application->application_number, $verificationMail->htmlContent());
        $this->assertStringContainsString($verificationMail->code, $verificationMail->htmlContent());
        $this->assertSame(0, User::count());

        $retryPayload = $this->applicationPayload('applicant@example.com');
        $retryPayload['submission_token'] = $submissionToken;
        $retry = $this->browser()->post('/api/public/member-applications', $retryPayload, ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJsonPath('data.application.uuid', $application->uuid)
            ->assertJsonPath('data.application_number', $application->application_number);
        $this->captureCookies($retry);
        $this->assertSame(1, MemberApplication::count());
        $this->assertSame(1, DB::table('member_submission_locks')->count());

        $this->browser()->postJson('/api/applicant/login', [
            'email' => 'applicant@example.com',
            'application_number' => 'APP-NOT-VALID',
        ])->assertStatus(422);
        $login = $this->browser()->postJson('/api/applicant/login', [
            'email' => 'applicant@example.com',
            'application_number' => strtolower($application->application_number),
        ])->assertSuccessful();
        $this->captureCookies($login);
        $sessionMarker = Str::random(64);
        $this->withSession([
            'applicant_application_id' => $application->id,
            'applicant_session_id' => $sessionMarker,
        ]);
        ApplicantSession::create([
            'session_id' => $sessionMarker,
            'member_application_id' => $application->id,
            'created_at' => now(),
            'last_seen_at' => now(),
        ]);

        $code = Mail::sent(VerificationCodeMail::class)->last()->code;
        $this->postJson('/api/applicant/email/verify', ['code' => $code])
            ->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::SUBMITTED);

        $emailUpdate = $this->putJson('/api/applicant/application', [
            'email' => 'changed@example.com',
        ])->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::PENDING_EMAIL);
        $this->assertSame('changed@example.com', $emailUpdate->json('data.application.email'));
        $newCode = Mail::sent(VerificationCodeMail::class)->last()->code;
        $this->postJson('/api/applicant/email/verify', ['code' => $newCode])
            ->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::SUBMITTED);

        $ordinary = $this->makeMember('Ordinary Member', '1991-01-01', 'Depok', '2011-01-01', [
            'account_claimed_at' => now(),
        ]);
        Sanctum::actingAs($ordinary);
        $this->putJson('/api/profile', ['email' => 'changed@example.com'])
            ->assertStatus(422);
        $this->assertNotSame('changed@example.com', $ordinary->fresh()->email);
        $this->withHeader('Origin', 'http://external.test')->getJson('/api/member-applications')->assertStatus(403);

        $admin = $this->makeUser('admin', ['account_claimed_at' => now()]);
        Sanctum::actingAs($admin);
        $this->withHeader('Origin', 'http://external.test')->putJson("/api/member-applications/{$application->uuid}/under-review")
            ->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::UNDER_REVIEW);
        $approve = $this->putJson("/api/member-applications/{$application->uuid}/approve")
            ->assertSuccessful();

        $firstId = $approve->json('data.id_anggota');
        $this->assertMatchesRegularExpression('/^'.now()->format('Y').'\d{6}$/', $firstId);
        $approved = User::where('id_anggota', $firstId)->firstOrFail();
        $this->assertFalse(Hash::check('mzt12345', $approved->password));
        $this->assertNull($approved->password_changed_at);
        $this->assertTrue($approved->account_setup_required);
        $this->assertNull($approved->account_claimed_at);
        $this->assertSame(['anggota', 'profil'], HakAksesRole::where('id_users', $approved->id)->orderBy('nama_role')->pluck('nama_role')->all());
        $this->assertSame(1, DB::table('data_users')->where('id_users', $approved->id)->count());
        $this->assertNotNull($approved->email_verified_at);
        $this->assertDatabaseMissing('applicant_sessions', ['member_application_id' => $application->id]);
        $approvalMail = Mail::sent(MemberApplicationApprovedMail::class)->last();
        $this->assertSame($firstId, $approvalMail->memberId);
        $this->assertSame($application->application_number, $approvalMail->applicationNumber);
        $this->assertSame('changed@example.com', $approvalMail->email);
        $this->assertSame(
            'https://members.example.test/reset-password?'.http_build_query([
                'token' => $approvalMail->token,
                'email' => 'changed@example.com',
            ]),
            $approvalMail->claimUrl()
        );
        $this->assertStringContainsString($firstId, $approvalMail->htmlContent());
        $this->assertStringContainsString($application->application_number, $approvalMail->htmlContent());
        $this->assertStringContainsString($approvalMail->claimUrl(), html_entity_decode($approvalMail->htmlContent()));
        $this->assertSame(1, DB::table('password_reset_requests')->where('user_id', $approved->id)->whereNull('used_at')->count());

        $approvedLogin = $this->browser()->postJson('/api/applicant/login', [
            'email' => 'changed@example.com',
            'application_number' => $application->application_number,
        ])->assertSuccessful()
            ->assertJsonPath('data.application.status', MemberApplication::APPROVED)
            ->assertJsonPath('data.application.id_anggota', $firstId);
        $this->captureCookies($approvedLogin);

        app('auth')->forgetGuards();
        $this->postJson('/api/login', [
            'id_anggota' => $firstId,
            'password' => 'mzt12345',
        ])->assertStatus(422);

        $this->postJson('/api/public/password/reset', [
            'token' => $approvalMail->token,
            'email' => 'changed@example.com',
            'password' => 'NewApprovedPassword2!',
            'password_confirmation' => 'NewApprovedPassword2!',
        ])->assertSuccessful();
        $approved->refresh();
        $this->assertTrue(Hash::check('NewApprovedPassword2!', $approved->password));
        $this->assertFalse($approved->account_setup_required);
        $this->assertNotNull($approved->account_claimed_at);
        $this->assertNotNull($approved->password_changed_at);
        $this->assertDatabaseMissing('applicant_sessions', ['member_application_id' => $application->id]);
        app('auth')->forgetGuards();
        $this->postJson('/api/login', [
            'id_anggota' => $firstId,
            'password' => 'NewApprovedPassword2!',
        ])->assertSuccessful()
            ->assertJsonPath('user.account_setup_required', false);
        $this->browser()->getJson('/api/applicant/me')->assertStatus(401);
        $this->browser()->postJson('/api/applicant/login', [
            'email' => 'changed@example.com',
            'application_number' => 'APP-NOT-VALID',
        ])->assertStatus(422);
        $this->browser()->postJson('/api/applicant/login', [
            'email' => 'changed@example.com',
            'application_number' => $application->application_number,
        ])->assertSuccessful()
            ->assertJsonPath('data.application.id_anggota', $firstId);

        $second = $this->makeSubmittedApplication('second@example.com');
        Sanctum::actingAs($admin);
        $secondApprove = $this->putJson("/api/member-applications/{$second->uuid}/approve")->assertSuccessful();
        $secondId = $secondApprove->json('data.id_anggota');
        $this->assertSame((int) $firstId + 1, (int) $secondId);

        $legacyId = $ordinary->id_anggota;
        $this->actingAs($admin, 'web');
        $this->post('/tabel-anggota/edit', [
            'id_users' => $ordinary->id,
            'nama' => 'Ordinary Updated',
            'email' => $ordinary->email,
            'alamat' => 'Alamat baru',
            'no_hp' => '081234567890',
            'pekerjaan' => 'Pekerja',
            'niqobah' => 'Niqobah',
            'tempat_lahir' => 'Depok',
            'tanggal_lahir' => '1993-03-03',
            'tahun_masuk' => '2015-01-01',
            'tahun_keluar' => '2020-01-01',
            'barcode' => null,
        ])->assertSuccessful();
        $this->assertSame($legacyId, $ordinary->fresh()->id_anggota);
    }

    private function buildSchema(): void
    {
        foreach ([
            'member_role_logs', 'member_application_logs', 'member_id_counters', 'applicant_sessions',
            'member_application_email_verifications', 'member_applications', 'member_submission_locks', 'member_email_locks',
            'password_reset_requests', 'account_setup_email_verifications', 'account_activation_logs', 'account_activation_challenges',
            'sessions', 'personal_access_tokens', 'hak_akses_role', 'role_user', 'data_users', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('id_anggota')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->string('is_active')->default('1');
            $table->timestamp('last_login')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('account_setup_required')->default(false);
            $table->timestamp('account_claimed_at')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->timestamps();
        });

        Schema::create('data_users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_users');
            $table->string('no_hp')->nullable();
            $table->text('barcode')->nullable();
            $table->text('alamat');
            $table->string('pekerjaan');
            $table->string('niqobah');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir');
            $table->date('tahun_masuk');
            $table->date('tahun_keluar');
            $table->text('foto');
            $table->string('is_active')->default('1');
            $table->timestamps();
        });

        Schema::create('role_user', function ($table) {
            $table->id();
            $table->string('nama_role');
            $table->string('keterangan')->nullable();
            $table->string('is_active')->default('1');
            $table->timestamps();
        });

        Schema::create('hak_akses_role', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_users');
            $table->string('nama_role');
            $table->string('hak_akses')->default('access');
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

        Schema::create('account_activation_challenges', function ($table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->json('candidate_ids');
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_activation_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('account_setup_email_verifications', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_requests', function ($table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('member_email_locks', function ($table) {
            $table->string('email_hash', 64)->primary();
            $table->timestamps();
        });

        Schema::create('member_submission_locks', function ($table) {
            $table->string('submission_key_hash', 64)->primary();
            $table->timestamps();
        });

        Schema::create('member_applications', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('application_number', 40)->unique();
            $table->string('submission_key_hash', 64)->unique();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('email');
            $table->string('active_email')->nullable()->unique();
            $table->string('no_hp');
            $table->string('normalized_phone');
            $table->text('alamat');
            $table->string('pekerjaan');
            $table->string('niqobah');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->date('tahun_masuk');
            $table->date('tahun_keluar');
            $table->string('foto');
            $table->string('status');
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('approved_user_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('under_review_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('member_application_email_verifications', function ($table) {
            $table->id();
            $table->unsignedBigInteger('member_application_id');
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_sessions', function ($table) {
            $table->string('session_id')->primary();
            $table->unsignedBigInteger('member_application_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });

        Schema::create('member_id_counters', function ($table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number');
            $table->timestamps();
        });

        Schema::create('member_application_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('member_application_id');
            $table->string('event');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('source');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('member_role_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('member_user_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('old_roles');
            $table->json('new_roles');
            $table->json('added_roles');
            $table->json('removed_roles');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function truncate(array $tables): void
    {
        foreach ($tables as $table) {
            DB::table($table)->delete();
        }
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'id_anggota' => (string) random_int(1000000, 9999999),
            'is_active' => '1',
            'password_changed_at' => now(),
            'account_setup_required' => false,
        ], $attributes));
        $this->addRole($user, $role);

        return $user;
    }

    private function makeMember(string $name, string $birthDate, string $birthPlace, string $entryDate, array $attributes = []): User
    {
        $user = $this->makeUser('anggota', array_merge(['name' => $name], $attributes));
        $this->addRole($user, 'profil');
        DB::table('data_users')->insert([
            'id_users' => $user->id,
            'no_hp' => '08123456789',
            'barcode' => null,
            'alamat' => 'Jl. Test',
            'pekerjaan' => 'Pekerja',
            'niqobah' => 'Niqobah',
            'tempat_lahir' => $birthPlace,
            'tanggal_lahir' => $birthDate,
            'tahun_masuk' => $entryDate,
            'tahun_keluar' => '2020-01-01',
            'foto' => 'image/anggota/member.jpg',
            'is_active' => $attributes['is_active'] ?? '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function addRole(User $user, string $role): void
    {
        HakAksesRole::create([
            'id_users' => $user->id,
            'nama_role' => $role,
            'hak_akses' => 'access',
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

    private function applicationPayload(string $email): array
    {
        return [
            'name' => 'Applicant One',
            'email' => $email,
            'no_hp' => '081234567890',
            'alamat' => 'Jl. Applicant',
            'pekerjaan' => 'Pekerja',
            'niqobah' => 'Niqobah',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-05-05',
            'tahun_masuk' => '2012',
            'tahun_keluar' => '2018',
            'foto' => UploadedFile::fake()->image('applicant.jpg', 300, 400),
            'submission_token' => (string) Str::uuid(),
        ];
    }

    private function makeSubmittedApplication(string $email): MemberApplication
    {
        $photo = UploadedFile::fake()->image(Str::random(8).'.jpg', 300, 400)->store('image/member-applications', 'public');

        return MemberApplication::create([
            'uuid' => (string) Str::uuid(),
            'application_number' => 'APP-'.strtoupper(Str::random(20)),
            'submission_key_hash' => hash('sha256', Str::random(64)),
            'name' => 'Second Applicant',
            'normalized_name' => 'second applicant',
            'email' => $email,
            'active_email' => $email,
            'no_hp' => '0812000000',
            'normalized_phone' => '0812000000',
            'alamat' => 'Jl. Second',
            'pekerjaan' => 'Pekerja',
            'niqobah' => 'Niqobah',
            'tempat_lahir' => 'Bogor',
            'tanggal_lahir' => '1994-01-01',
            'tahun_masuk' => '2011-01-01',
            'tahun_keluar' => '2017-01-01',
            'foto' => $photo,
            'status' => MemberApplication::SUBMITTED,
            'email_verified_at' => now(),
        ]);
    }

    private function bootstrapBrowserSession(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:8080')->get('/sanctum/csrf-cookie');
        $response->assertStatus(204);
        $this->captureCookies($response);
    }

    private function browser()
    {
        app('auth')->forgetGuards();
        $cookies = [];
        if (isset($this->cookieJar[config('session.cookie')])) {
            $cookies[config('session.cookie')] = $this->cookieJar[config('session.cookie')];
        }

        return $this->withHeaders(['Origin' => 'http://localhost:8080'])
            ->withUnencryptedCookies($cookies);
    }

    private function captureCookies($response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookieJar[$cookie->getName()] = $cookie->getValue() ?? '';
        }
    }
}
