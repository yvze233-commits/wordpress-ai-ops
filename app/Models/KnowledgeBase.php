<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    protected $attributes = ['enabled' => true];

    protected $fillable = ['name', 'description', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }
}
