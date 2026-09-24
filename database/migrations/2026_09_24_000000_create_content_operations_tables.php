<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_batches', function (Blueprint $table): void {
            $table->id();
            $table->date('run_date')->unique();
            $table->unsignedSmallInteger('target_count')->default(4);
            $table->string('status')->default('planned');
            $table->unsignedSmallInteger('completed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('topic_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type');
            $table->string('source_key');
            $table->string('title');
            $table->string('normalized_title');
            $table->text('summary')->nullable();
            $table->text('source_url')->nullable();
            $table->string('event_fingerprint')->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->string('status')->default('candidate');
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_key']);
            $table->index(['status', 'priority']);
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_candidate_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content_html')->nullable();
            $table->string('state')->default('locked');
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('wordpress_post_id')->nullable();
            $table->text('wordpress_url')->nullable();
            $table->json('generation_meta')->nullable();
            $table->json('review_result')->nullable();
            $table->json('evidence_snapshot')->nullable();
            $table->timestamps();
            $table->index(['state', 'content_batch_id']);
        });

        Schema::create('content_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['content_item_id', 'event_type']);
        });

        Schema::create('wordpress_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('base_url');
            $table->string('username');
            $table->text('application_password_encrypted');
            $table->string('default_post_status')->default('draft');
            $table->json('category_mapping')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_health_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_connections');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('content_runs');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('topic_candidates');
        Schema::dropIfExists('content_batches');
    }
};
