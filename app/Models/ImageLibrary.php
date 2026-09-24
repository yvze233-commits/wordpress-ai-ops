<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageLibrary extends Model
{
    protected $fillable = ['name', 'description', 'enabled'];

    protected $attributes = ['enabled' => true];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    public function images(): HasMany
    {
        return $this->hasMany(LibraryImage::class);
    }
}
