<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('topic_window_hours')->default(72)->after('schedule_time');
            $table->text('topic_keywords')->nullable()->after('topic_window_hours');
            $table->unsignedTinyInteger('min_source_trust')->default(50)->after('topic_keywords');
            $table->foreignId('wordpress_connection_id')->nullable()->after('min_source_trust')->constrained('wordpress_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('wordpress_connection_id');
            $table->dropColumn(['topic_window_hours', 'topic_keywords', 'min_source_trust']);
        });
    }
};
