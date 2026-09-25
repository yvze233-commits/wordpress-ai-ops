<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('paused');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('daily_target')->default(4);
            $table->string('writing_skill_slug');
            $table->string('review_skill_slug')->default('geoflow_two_pass');
            $table->unsignedTinyInteger('review_pass_threshold')->default(70);
            $table->string('publish_status')->default('draft');
            $table->string('schedule_time')->default('02:00');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['status', 'enabled']);
        });

        Schema::table('content_batches', function (Blueprint $table): void {
            $table->dropUnique('content_batches_run_date_unique');
            $table->foreignId('content_task_id')->nullable()->after('id')->constrained('content_tasks')->nullOnDelete();
            $table->unique(['content_task_id', 'run_date']);
        });
    }

    public function down(): void
    {
        Schema::table('content_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_task_id');
        });
        Schema::dropIfExists('content_tasks');
    }
};
