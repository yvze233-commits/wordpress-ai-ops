<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_library_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('raw_title');
            $table->string('normalized_title');
            $table->json('keywords')->nullable();
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique('normalized_title');
            $table->index(['enabled', 'priority']);
        });

        Schema::create('daily_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_candidate_id')->constrained()->restrictOnDelete();
            $table->foreignId('title_library_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->decimal('selection_score', 10, 6)->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->string('result')->default('locked');
            $table->timestamps();
            $table->unique(['content_batch_id', 'topic_candidate_id']);
            $table->index(['content_batch_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_selections');
        Schema::dropIfExists('title_library_entries');
    }
};
