<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentItem extends Model
{
    protected $fillable = [
        'content_batch_id',
        'topic_candidate_id',
        'title',
        'slug',
        'excerpt',
        'content_html',
        'state',
        'idempotency_key',
        'wordpress_post_id',
        'wordpress_url',
        'generation_meta',
        'review_result',
        'evidence_snapshot',
        'writing_skill_snapshot',
        'review_skill_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'content_batch_id' => 'integer',
            'topic_candidate_id' => 'integer',
            'wordpress_post_id' => 'integer',
            'generation_meta' => 'array',
            'review_result' => 'array',
            'evidence_snapshot' => 'array',
            'writing_skill_snapshot' => 'array',
            'review_skill_snapshot' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ContentBatch::class, 'content_batch_id');
    }

    public function contentBatch(): BelongsTo
    {
        return $this->batch();
    }

    public function topicCandidate(): BelongsTo
    {
        return $this->belongsTo(TopicCandidate::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->topicCandidate();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ContentRun::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function imagePlacements(): HasMany
    {
        return $this->hasMany(ImagePlacement::class);
    }
}
