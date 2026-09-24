<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index('enabled');
        });

        Schema::create('knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('source_url')->nullable();
            $table->string('source_type')->default('text');
            $table->string('review_status')->default('pending');
            $table->string('risk_level')->default('low');
            $table->char('content_hash', 64);
            $table->unsignedInteger('content_length')->default(0);
            $table->text('text_content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['knowledge_base_id', 'content_hash']);
            $table->index(['knowledge_base_id', 'review_status', 'risk_level']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('heading')->nullable();
            $table->text('content');
            $table->char('content_hash', 64);
            $table->json('embedding_metadata')->nullable();
            $table->timestamps();
            $table->unique(['knowledge_document_id', 'position']);
            $table->unique(['knowledge_document_id', 'content_hash']);
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_bases');
    }
};
