<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRun extends Model
{
    protected $fillable = ['content_item_id', 'stage', 'attempt', 'status', 'error_message', 'payload'];

    protected function casts(): array
    {
        return [
            'content_item_id' => 'integer',
            'attempt' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->contentItem();
    }
}
