<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillVersion extends Model
{
    protected $fillable = [
        'skill_id', 'version', 'raw_text', 'parsed_rules', 'output_schema', 'prohibited_terms',
        'pass_threshold', 'validation_report', 'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'skill_id' => 'integer',
            'version' => 'integer',
            'parsed_rules' => 'array',
            'output_schema' => 'array',
            'prohibited_terms' => 'array',
            'pass_threshold' => 'integer',
            'validation_report' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
