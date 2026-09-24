<?php

namespace Tests\Unit;

use App\Domain\Topics\DuplicateDetector;
use App\Domain\Topics\TopicLockService;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_title_url_and_event_duplicates_are_rejected(): void
    {
        $first = TopicCandidate::create([
            'source_type' => 'rss',
            'source_key' => 'one',
            'title' => '人工智能 教育',
            'normalized_title' => '人工智能 教育',
            'source_url' => 'https://example.test/one',
            'event_fingerprint' => str_repeat('a', 64),
        ]);
        $sameTitle = TopicCandidate::create([
            'source_type' => 'rss',
            'source_key' => 'two',
            'title' => '人工智能 教育',
            'normalized_title' => '人工智能 教育',
            'source_url' => 'https://example.test/two',
            'event_fingerprint' => str_repeat('b', 64),
        ]);
        $sameUrl = TopicCandidate::create([
            'source_type' => 'api',
            'source_key' => 'three',
            'title' => '其他标题',
            'normalized_title' => '其他标题',
            'source_url' => 'https://example.test/one',
            'event_fingerprint' => str_repeat('c', 64),
        ]);
        $sameEvent = TopicCandidate::create([
            'source_type' => 'api',
            'source_key' => 'four',
            'title' => '另一个标题',
            'normalized_title' => '另一个标题',
            'source_url' => 'https://example.test/four',
            'event_fingerprint' => str_repeat('a', 64),
        ]);
        $detector = new DuplicateDetector;

        $this->assertTrue($detector->isDuplicate($sameTitle, collect([$first])));
        $this->assertTrue($detector->isDuplicate($sameUrl, collect([$first])));
        $this->assertTrue($detector->isDuplicate($sameEvent, collect([$first])));
    }

    public function test_high_similarity_and_successful_content_are_rejected(): void
    {
        $first = TopicCandidate::create([
            'source_type' => 'rss',
            'source_key' => 'one',
            'title' => '人工智能教育发展趋势与未来',
            'normalized_title' => '人工智能教育发展趋势与未来',
        ]);
        $similar = TopicCandidate::create([
            'source_type' => 'rss',
            'source_key' => 'two',
            'title' => '人工智能教育发展趋势和未来',
            'normalized_title' => '人工智能教育发展趋势和未来',
        ]);
        $successful = TopicCandidate::create([
            'source_type' => 'rss',
            'source_key' => 'three',
            'title' => '已经写入 WordPress 的文章',
            'normalized_title' => '已经写入 wordpress 的文章',
        ]);
        ContentItem::create([
            'topic_candidate_id' => $successful->id,
            'title' => $successful->title,
            'state' => 'wp_draft_written',
            'idempotency_key' => 'successful-item',
        ]);
        $detector = new DuplicateDetector;

        $this->assertTrue($detector->isDuplicate($similar, collect([$first])));
        $this->assertTrue($detector->isDuplicate($successful));
    }

    public function test_candidate_lock_can_be_acquired_and_released(): void
    {
        $candidate = TopicCandidate::create([
            'source_type' => 'rss',
            'source_key' => 'one',
            'title' => '可锁定选题',
            'normalized_title' => '可锁定选题',
        ]);
        $locks = new TopicLockService;

        $locked = $locks->acquire($candidate, 120);
        $this->assertNotNull($locked);
        $this->assertSame('locked', $locked->fresh()->status);
        $this->assertNotNull($locked->fresh()->locked_until);

        $locks->release($locked);
        $this->assertSame('candidate', $candidate->fresh()->status);
        $this->assertNull($candidate->fresh()->locked_until);
    }
}
