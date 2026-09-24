<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConnection extends Model
{
    protected $fillable = ['name', 'provider', 'base_url', 'api_key_encrypted', 'enabled', 'status', 'last_health_checked_at', 'last_error'];

    protected $hidden = ['api_key_encrypted'];

    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
            'enabled' => 'boolean',
            'last_health_checked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
