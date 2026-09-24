<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleLibraryEntry extends Model
{
    protected $fillable = [
        'raw_title',
        'normalized_title',
        'keywords',
        'category',
        'priority',
        'enabled',
        'use_count',
        'last_used_at',
        'content_item_id',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'priority' => 'integer',
            'enabled' => 'boolean',
            'use_count' => 'integer',
            'last_used_at' => 'datetime',
            'content_item_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }
}
