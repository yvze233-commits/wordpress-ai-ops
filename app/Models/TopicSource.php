<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicSource extends Model
{
    protected $fillable = [
        'name',
        'type',
        'url',
        'parser_config',
        'trust_score',
        'enabled',
        'status',
        'last_fetched_at',
        'last_http_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'parser_config' => 'array',
            'trust_score' => 'integer',
            'enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_http_status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function feeds(): HasMany
    {
        return $this->hasMany(TopicFeed::class);
    }
}
