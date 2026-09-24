<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySelection extends Model
{
    protected $fillable = [
        'content_batch_id',
        'topic_candidate_id',
        'title_library_entry_id',
        'source',
        'selection_score',
        'locked_until',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'content_batch_id' => 'integer',
            'topic_candidate_id' => 'integer',
            'title_library_entry_id' => 'integer',
            'selection_score' => 'float',
            'locked_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ContentBatch::class, 'content_batch_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(TopicCandidate::class, 'topic_candidate_id');
    }

    public function titleLibraryEntry(): BelongsTo
    {
        return $this->belongsTo(TitleLibraryEntry::class);
    }
}
