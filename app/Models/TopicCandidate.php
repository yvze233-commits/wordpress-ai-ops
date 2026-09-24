<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicCandidate extends Model
{
    protected $fillable = [
        'source_type',
        'source_key',
        'title',
        'normalized_title',
        'summary',
        'source_url',
        'event_fingerprint',
        'priority',
        'status',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'locked_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    public function items(): HasMany
    {
        return $this->contentItems();
    }

    public function dailySelections(): HasMany
    {
        return $this->hasMany(DailySelection::class);
    }
}
