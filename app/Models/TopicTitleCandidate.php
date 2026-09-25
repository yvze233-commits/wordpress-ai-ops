<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicTitleCandidate extends Model
{
    protected $fillable = [
        'topic_candidate_id', 'title', 'normalized_title', 'rationale', 'source_snapshot', 'score', 'status', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'topic_candidate_id' => 'integer',
            'source_snapshot' => 'array',
            'score' => 'integer',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function topicCandidate(): BelongsTo
    {
        return $this->belongsTo(TopicCandidate::class);
    }
}
