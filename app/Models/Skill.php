<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    protected $fillable = ['name', 'slug', 'kind', 'source', 'enabled', 'current_version'];

    protected $attributes = ['enabled' => true, 'current_version' => 1];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'current_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SkillVersion::class);
    }

    public function currentVersion(): ?SkillVersion
    {
        return $this->versions()->where('version', $this->current_version)->first();
    }
}
