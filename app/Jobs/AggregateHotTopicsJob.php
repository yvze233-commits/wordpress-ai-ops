<?php

namespace App\Jobs;

use App\Domain\Topics\HotTopicAggregator;
use App\Domain\Topics\TopicFingerprint;
use App\Domain\Topics\TopicNormalizer;
use App\Models\AuditEvent;
use App\Models\ContentTask;
use App\Models\TopicCandidate;
use App\Models\TopicFeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class AggregateHotTopicsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $taskId) {}

    public function handle(HotTopicAggregator $aggregator): int
    {
        $task = ContentTask::query()->findOrFail($this->taskId);
        $items = $aggregator->collect($task);
        $normalizer = new TopicNormalizer;
        $count = 0;

        foreach ($items as $item) {
            $sourceKey = trim((string) ($item['source_key'] ?? '')) ?: TopicFingerprint::forEvent($item);
            $normalizedTitle = $normalizer->title($item['title'] ?? null);
            $normalizedUrl = $normalizer->url($item['url'] ?? null);
            $fingerprint = TopicFingerprint::forEvent([...$item, 'title' => $normalizedTitle, 'url' => $normalizedUrl]);
            DB::transaction(function () use ($item, $task, $sourceKey, $normalizedTitle, $normalizedUrl, $fingerprint, &$count): void {
                $source = TopicSource::query()->firstOrCreate(
                    ['name' => (string) ($item['source_name'] ?? '系统热点聚合源'), 'type' => 'rss'],
                    ['url' => (string) ($item['source_url'] ?? 'https://news.google.com/'), 'trust_score' => (int) ($item['trust_score'] ?? 50)],
                );
                TopicFeed::query()->updateOrCreate(
                    ['topic_source_id' => $source->id, 'source_key' => $sourceKey],
                    ['title' => $item['title'], 'normalized_title' => $normalizedTitle, 'url' => $item['url'] ?? null, 'normalized_url' => $normalizedUrl, 'event_fingerprint' => $fingerprint, 'summary' => $item['summary'] ?? null, 'published_at' => $item['published_at'] ?? null, 'raw_payload' => $item['raw_payload'] ?? null],
                );
                TopicCandidate::query()->updateOrCreate(
                    ['source_type' => 'rss', 'source_key' => $sourceKey],
                    ['title' => $item['title'], 'normalized_title' => $normalizedTitle, 'summary' => $item['summary'] ?? null, 'source_url' => $normalizedUrl, 'event_fingerprint' => $fingerprint, 'priority' => (int) ($item['trust_score'] ?? 50)],
                );
                $count++;
            });
        }

        AuditEvent::query()->create(['event_type' => 'hot_topics_aggregated', 'payload' => ['task_id' => $task->id, 'item_count' => $count, 'collected_at' => now()->toIso8601String()]]);

        return $count;
    }

    public function failed(?Throwable $exception): void
    {
        AuditEvent::query()->create(['event_type' => 'hot_topics_aggregation_failed', 'payload' => ['task_id' => $this->taskId, 'error' => $exception?->getMessage()]]);
    }
}
