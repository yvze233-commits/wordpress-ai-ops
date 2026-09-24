<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind');
            $table->string('source')->default('user');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();
            $table->index(['kind', 'enabled']);
        });

        Schema::create('skill_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('raw_text');
            $table->json('parsed_rules');
            $table->json('output_schema');
            $table->json('prohibited_terms')->nullable();
            $table->unsignedSmallInteger('pass_threshold')->nullable();
            $table->json('validation_report');
            $table->char('content_hash', 64);
            $table->timestamps();
            $table->unique(['skill_id', 'version']);
            $table->unique(['skill_id', 'content_hash']);
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->json('writing_skill_snapshot')->nullable()->after('evidence_snapshot');
            $table->json('review_skill_snapshot')->nullable()->after('writing_skill_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropColumn(['writing_skill_snapshot', 'review_skill_snapshot']);
        });
        Schema::dropIfExists('skill_versions');
        Schema::dropIfExists('skills');
    }
};
