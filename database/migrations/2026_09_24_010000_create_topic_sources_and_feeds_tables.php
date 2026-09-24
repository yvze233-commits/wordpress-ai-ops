<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->text('url');
            $table->json('parser_config')->nullable();
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->boolean('enabled')->default(true);
            $table->string('status')->default('active');
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['enabled', 'type']);
            $table->index('status');
        });

        Schema::create('topic_feeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_source_id')->constrained()->cascadeOnDelete();
            $table->string('source_key');
            $table->string('title');
            $table->string('normalized_title');
            $table->text('url')->nullable();
            $table->string('normalized_url', 2048)->nullable();
            $table->string('event_fingerprint', 64)->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['topic_source_id', 'source_key']);
            $table->index(['topic_source_id', 'published_at']);
            $table->index('normalized_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_feeds');
        Schema::dropIfExists('topic_sources');
    }
};
