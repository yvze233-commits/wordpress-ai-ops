<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    protected $fillable = [
        'knowledge_base_id',
        'name',
        'source_url',
        'source_type',
        'review_status',
        'risk_level',
        'content_hash',
        'content_length',
        'text_content',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'knowledge_base_id' => 'integer',
            'content_length' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }
}
