<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    protected $fillable = ['content_item_id', 'event_type', 'payload'];

    protected function casts(): array
    {
        return [
            'content_item_id' => 'integer',
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
