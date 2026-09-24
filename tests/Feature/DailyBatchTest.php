<?php

namespace Tests\Feature;

use App\Jobs\CreateDailyBatchJob;
use App\Models\ContentBatch;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_batch_selects_four_unique_candidates_and_is_idempotent(): void
    {
        foreach (range(1, 6) as $index) {
            TopicCandidate::create([
                'source_type' => $index % 2 === 0 ? 'title_library' : 'rss',
                'source_key' => 'candidate-'.$index,
                'title' => '每日选题 '.$index,
                'normalized_title' => '每日选题 '.$index,
                'priority' => $index === 1 ? 90 : 50,
            ]);
        }

        $first = (new CreateDailyBatchJob('2026-09-24'))->handle();
        $second = (new CreateDailyBatchJob('2026-09-24'))->handle();

        $this->assertInstanceOf(ContentBatch::class, $first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame(4, $first->fresh()->dailySelections()->count());
        $this->assertSame(4, $first->fresh()->contentItems()->where('state', 'locked')->count());
        $this->assertSame(4, $first->fresh()->dailySelections()->pluck('topic_candidate_id')->unique()->count());
        $this->assertSame(4, ContentItem::query()->count());
        $item = ContentItem::query()->firstOrFail();
        $this->assertSame('GEOFlow 印象文生成', $item->writing_skill_snapshot['name']);
        $this->assertSame('geoflow_two_pass', $item->review_skill_snapshot['strategy']);
        $this->assertCount(2, $item->review_skill_snapshot['stages']);
    }

    public function test_daily_batch_marks_a_shortfall_when_only_two_candidates_exist(): void
    {
        foreach (range(1, 2) as $index) {
            TopicCandidate::create([
                'source_type' => 'rss',
                'source_key' => 'shortfall-'.$index,
                'title' => '不足选题 '.$index,
                'normalized_title' => '不足选题 '.$index,
            ]);
        }

        $batch = (new CreateDailyBatchJob('2026-09-25'))->handle();

        $this->assertSame('shortfall', $batch->fresh()->status);
        $this->assertSame(2, $batch->fresh()->completed_count);
        $this->assertSame(2, $batch->fresh()->dailySelections()->count());
    }
}
