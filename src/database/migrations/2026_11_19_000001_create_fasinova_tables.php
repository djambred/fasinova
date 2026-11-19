<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('nim')->nullable()->unique();
            $t->string('nidn')->nullable();
            $t->string('sso_subject')->nullable()->unique();
            $t->string('study_program')->nullable();
            $t->string('role')->default('mahasiswa'); // mahasiswa|pembimbing|pic|admin|sales
        });

        Schema::create('tracks', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('repo_prefix')->nullable();
            $t->unsignedInteger('default_port')->nullable();
            $t->boolean('deployable')->default(false);
            $t->json('required_files')->nullable();
            $t->json('checklist')->nullable();
            $t->json('result_card_schema')->nullable();
            $t->json('forbidden_images')->nullable();
            $t->string('sop_url')->nullable();
            $t->timestamps();
        });

        Schema::create('track_pics', function (Blueprint $t) {
            $t->foreignId('track_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['track_id', 'user_id']);
        });

        Schema::create('innovations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('slug')->unique();
            $t->string('title');
            $t->foreignId('track_id')->constrained();
            $t->foreignId('owner_id')->constrained('users');
            $t->string('study_program')->nullable();
            $t->string('academic_period')->nullable();
            $t->text('problem')->nullable();
            $t->text('solution')->nullable();
            // draft|submitted|needs_revision|approved|rejected|published|archived
            $t->string('status')->default('draft');
            $t->foreignId('supervisor_id')->nullable()->constrained('users');
            $t->timestamp('supervisor_approved_at')->nullable();
            $t->timestamp('revision_due_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->string('video_url')->nullable();
            $t->json('materials')->nullable(); // path berkas: poster, screenshots, consent, etika
            $t->json('members')->nullable();   // [{nim,name,role}]
            $t->boolean('consent_ok')->default(false);
            $t->boolean('safe_service_ok')->default(false); // tanda PIC untuk Cyber Security
            $t->timestamps();
            $t->index(['track_id', 'status']);
        });

        Schema::create('repositories', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('innovation_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('url');
            $t->string('owner')->nullable();
            $t->string('name')->nullable();
            $t->string('tag')->default('v1.0-showcase');
            $t->string('locked_commit_sha')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->json('validation_report')->nullable();
            $t->timestamps();
        });

        Schema::create('env_variables', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('innovation_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->text('value')->nullable(); // encrypted cast
            $t->boolean('is_secret')->default(true);
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['innovation_id', 'key']);
        });

        Schema::create('deployments', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('innovation_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('subdomain')->unique();
            $t->string('public_service')->nullable();
            $t->unsignedInteger('internal_port')->nullable();
            $t->unsignedInteger('host_port')->nullable()->unique();
            $t->string('health_path')->default('/');
            $t->string('status')->default('not_deployed')->index();
            $t->string('commit_sha')->nullable();
            $t->unsignedBigInteger('npm_proxy_host_id')->nullable();
            $t->string('url')->nullable();
            $t->decimal('cpu_limit', 4, 2)->default(0.5);
            $t->unsignedInteger('mem_limit_mb')->default(512);
            $t->json('allowed_caps')->nullable();
            $t->boolean('needs_redeploy')->default(false);
            $t->unsignedTinyInteger('health_failures')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamp('last_health_at')->nullable();
            $t->foreignId('triggered_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('deployment_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $t->string('step');
            $t->string('stream')->default('out');
            $t->text('line');
            $t->timestamp('created_at')->useCurrent();
            $t->index('deployment_id');
        });

        Schema::create('deploy_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('triggered_by')->nullable()->constrained('users');
            $t->string('commit_sha')->nullable();
            $t->string('result');
            $t->unsignedInteger('duration_s')->nullable();
            $t->timestamps();
        });

        Schema::create('result_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('innovation_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('data')->nullable();
            $t->json('claim_warnings')->nullable();
            $t->timestamps();
        });

        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('innovation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users');
            $t->json('checklist_result')->nullable();
            $t->string('decision'); // approve|return|reject
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('innovation_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('organization')->nullable();
            $t->text('message');
            $t->string('status')->default('new'); // new|in_progress|done
            $t->foreignId('assignee_id')->nullable()->constrained('users');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable(); // npm_password dll. disimpan terenkripsi oleh Setting::put
        });
    }
};
