<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagePlacement extends Model
{
    protected $fillable = [
        'content_item_id', 'library_image_id', 'role', 'paragraph_index', 'paragraph_anchor',
        'confidence', 'reason', 'position', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'content_item_id' => 'integer', 'library_image_id' => 'integer', 'paragraph_index' => 'integer',
            'confidence' => 'float', 'position' => 'integer', 'metadata' => 'array',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(LibraryImage::class, 'library_image_id');
    }
}
