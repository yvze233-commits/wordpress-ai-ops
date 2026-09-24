<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentBatch extends Model
{
    protected $fillable = ['run_date', 'target_count', 'status', 'completed_count'];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'target_count' => 'integer',
            'completed_count' => 'integer',
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
