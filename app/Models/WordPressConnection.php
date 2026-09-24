<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressConnection extends Model
{
    protected $table = 'wordpress_connections';

    protected $hidden = ['application_password_encrypted'];

    protected $fillable = [
        'name',
        'base_url',
        'username',
        'application_password_encrypted',
        'default_post_status',
        'category_mapping',
        'settings',
        'status',
        'last_health_checked_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'application_password_encrypted' => 'encrypted',
            'category_mapping' => 'array',
            'settings' => 'array',
            'last_health_checked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
