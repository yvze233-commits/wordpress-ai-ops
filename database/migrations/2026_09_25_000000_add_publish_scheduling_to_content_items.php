<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('publish_status')->nullable()->after('state');
            $table->timestamp('scheduled_publish_at')->nullable()->after('publish_status');
            $table->index('scheduled_publish_at');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_publish_at']);
            $table->dropColumn(['publish_status', 'scheduled_publish_at']);
        });
    }
};
