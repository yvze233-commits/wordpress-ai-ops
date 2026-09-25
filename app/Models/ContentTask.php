<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentTask extends Model
{
    protected $fillable = [
        'name', 'status', 'enabled', 'daily_target', 'writing_skill_slug', 'review_skill_slug',
        'review_pass_threshold', 'publish_status', 'schedule_time', 'topic_window_hours', 'topic_keywords',
        'min_source_trust', 'wordpress_connection_id', 'settings',
    ];

    protected $attributes = [
        'status' => 'paused', 'enabled' => true, 'daily_target' => 4,
        'review_pass_threshold' => 70, 'publish_status' => 'draft', 'schedule_time' => '02:00',
        'topic_window_hours' => 72, 'min_source_trust' => 50,
        'settings' => '{"topic_source_mode":"both","require_images":true,"require_source_links":true}',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'daily_target' => 'integer', 'review_pass_threshold' => 'integer', 'topic_window_hours' => 'integer', 'min_source_trust' => 'integer', 'wordpress_connection_id' => 'integer', 'settings' => 'array', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ContentBatch::class);
    }
}
