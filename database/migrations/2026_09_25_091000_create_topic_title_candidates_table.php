<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_title_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_candidate_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('normalized_title');
            $table->text('rationale')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('status')->default('available');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->unique(['topic_candidate_id', 'normalized_title']);
            $table->index(['status', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_title_candidates');
    }
};
