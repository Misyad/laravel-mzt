<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('account_setup_required')->default(false)->after('password_changed_at');
            $table->timestamp('account_claimed_at')->nullable()->after('account_setup_required');
        });

        Schema::create('account_activation_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->json('candidate_ids');
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index('expires_at');
        });

        Schema::create('account_activation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event', 60);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        Schema::create('account_setup_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });

        Schema::create('member_email_locks', function (Blueprint $table) {
            $table->string('email_hash', 64)->primary();
            $table->timestamps();
        });

        Schema::create('member_submission_locks', function (Blueprint $table) {
            $table->string('submission_key_hash', 64)->primary();
            $table->timestamps();
        });

        Schema::create('member_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('application_number', 40)->unique();
            $table->string('submission_key_hash', 64)->unique();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('email');
            $table->string('active_email')->nullable()->unique();
            $table->string('no_hp', 40);
            $table->string('normalized_phone', 40);
            $table->text('alamat');
            $table->string('pekerjaan');
            $table->string('niqobah');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->date('tahun_masuk');
            $table->date('tahun_keluar');
            $table->string('foto');
            $table->string('status', 30)->default('pending_email');
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('approved_user_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('under_review_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('email');
            $table->index(['normalized_name', 'tanggal_lahir'], 'member_application_identity_idx');
            $table->index(['normalized_name', 'tanggal_lahir', 'normalized_phone'], 'member_application_identity_phone_idx');
        });

        Schema::create('member_application_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_application_id');
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['member_application_id', 'created_at'], 'member_application_email_created_idx');
        });

        Schema::create('applicant_sessions', function (Blueprint $table) {
            $table->string('session_id')->primary();
            $table->unsignedBigInteger('member_application_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->index('member_application_id');
        });

        Schema::create('member_id_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('member_application_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_application_id');
            $table->string('event', 60);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('source', 30);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['member_application_id', 'created_at'], 'member_application_logs_created_idx');
        });

        Schema::create('member_role_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_user_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('old_roles');
            $table->json('new_roles');
            $table->json('added_roles');
            $table->json('removed_roles');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['member_user_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('member_role_logs');
        Schema::dropIfExists('member_application_logs');
        Schema::dropIfExists('member_id_counters');
        Schema::dropIfExists('applicant_sessions');
        Schema::dropIfExists('member_application_email_verifications');
        Schema::dropIfExists('member_applications');
        Schema::dropIfExists('member_submission_locks');
        Schema::dropIfExists('member_email_locks');
        Schema::dropIfExists('password_reset_requests');
        Schema::dropIfExists('account_setup_email_verifications');
        Schema::dropIfExists('account_activation_logs');
        Schema::dropIfExists('account_activation_challenges');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_setup_required', 'account_claimed_at']);
        });
    }
};
