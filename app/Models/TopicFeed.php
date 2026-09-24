<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicFeed extends Model
{
    protected $fillable = [
        'topic_source_id',
        'source_key',
        'title',
        'normalized_title',
        'url',
        'normalized_url',
        'event_fingerprint',
        'summary',
        'published_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'topic_source_id' => 'integer',
            'published_at' => 'datetime',
            'raw_payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TopicSource::class, 'topic_source_id');
    }
}
