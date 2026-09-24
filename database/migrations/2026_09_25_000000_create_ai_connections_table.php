<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider');
            $table->text('base_url')->nullable();
            $table->text('api_key_encrypted');
            $table->boolean('enabled')->default(true);
            $table->string('status')->default('active');
            $table->timestamp('last_health_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connections');
    }
};
