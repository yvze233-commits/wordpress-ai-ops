<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'knowledge_document_id',
        'position',
        'heading',
        'content',
        'content_hash',
        'embedding_metadata',
    ];

    protected function casts(): array
    {
        return [
            'knowledge_document_id' => 'integer',
            'position' => 'integer',
            'embedding_metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }
}
