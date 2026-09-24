<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_libraries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('library_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('image_library_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('notes')->nullable();
            $table->json('keywords')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->json('scenes')->nullable();
            $table->string('copyright_state')->default('unknown');
            $table->text('ocr_text')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['image_library_id', 'enabled']);
            $table->index('copyright_state');
        });

        Schema::create('image_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_image_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->unsignedInteger('paragraph_index')->nullable();
            $table->string('paragraph_anchor')->nullable();
            $table->decimal('confidence', 6, 4)->default(0);
            $table->text('reason')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['content_item_id', 'library_image_id']);
            $table->index(['content_item_id', 'role', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_placements');
        Schema::dropIfExists('library_images');
        Schema::dropIfExists('image_libraries');
    }
};
