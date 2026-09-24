<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryImage extends Model
{
    protected $fillable = [
        'image_library_id', 'path', 'mime_type', 'width', 'height', 'caption', 'alt_text', 'notes',
        'keywords', 'applicable_categories', 'scenes', 'copyright_state', 'ocr_text', 'usage_count', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'image_library_id' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'keywords' => 'array', 'applicable_categories' => 'array', 'scenes' => 'array',
            'usage_count' => 'integer', 'enabled' => 'boolean',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'image_library_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(ImagePlacement::class);
    }
}
