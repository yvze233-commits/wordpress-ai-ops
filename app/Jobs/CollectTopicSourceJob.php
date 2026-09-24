<?php

namespace App\Jobs;

use App\Domain\Topics\HtmlTopicSourceConnector;
use App\Domain\Topics\JsonApiTopicSourceConnector;
use App\Domain\Topics\MalformedTopicSourceException;
use App\Domain\Topics\RssTopicSourceConnector;
use App\Domain\Topics\TopicFingerprint;
use App\Domain\Topics\TopicNormalizer;
use App\Domain\Topics\TopicSourceConnector;
use App\Domain\Topics\TopicSourceException;
use App\Models\AuditEvent;
use App\Models\TopicCandidate;
use App\Models\TopicFeed;
use App\Models\TopicSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\SerializesModels;

class CollectTopicSourceJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries;

    public function __construct(public readonly int $topicSourceId)
    {
        $this->tries = (int) config('content-ops.topic_source_max_attempts', 3);
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(?DatabaseManager $database = null): int
    {
        $database ??= app(DatabaseManager::class);
        $source = TopicSource::query()->findOrFail($this->topicSourceId);

        if (! $source->enabled || $source->status === 'paused') {
            return 0;
        }

        try {
            $items = $this->connector($source)->collect($source);
        } catch (RequestException $exception) {
            return $this->handleRequestFailure($source, $exception);
        } catch (TopicSourceException $exception) {
            $this->recordFailure($source, $exception->getMessage());
            if ($exception->retryable) {
                throw $exception;
            }

            return 0;
        }

        $normalizer = new TopicNormalizer;
        $count = $database->transaction(function () use ($items, $normalizer, $source): int {
            $count = 0;

            foreach ($items as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $normalizedTitle = $normalizer->title($title);
                $normalizedUrl = $normalizer->url($item['url'] ?? null);
                $sourceKey = trim((string) ($item['source_key'] ?? ''));
                $sourceKey = $sourceKey !== '' ? $sourceKey : TopicFingerprint::forEvent($item);
                $eventFingerprint = TopicFingerprint::forEvent([
                    ...$item,
                    'url' => $normalizedUrl,
                    'title' => $normalizedTitle,
                ]);
                $publishedAt = $item['published_at'] ?? null;
                $rawPayload = is_array($item['raw_payload'] ?? null) ? $item['raw_payload'] : null;

                TopicFeed::query()->updateOrCreate(
                    ['topic_source_id' => $source->id, 'source_key' => $sourceKey],
                    [
                        'title' => $title,
                        'normalized_title' => $normalizedTitle,
                        'url' => $item['url'] ?? null,
                        'normalized_url' => $normalizedUrl,
                        'event_fingerprint' => $eventFingerprint,
                        'summary' => $item['summary'] ?? null,
                        'published_at' => $publishedAt,
                        'raw_payload' => $rawPayload,
                    ],
                );

                TopicCandidate::query()->updateOrCreate(
                    ['source_type' => $source->type, 'source_key' => $sourceKey],
                    [
                        'title' => $title,
                        'normalized_title' => $normalizedTitle,
                        'summary' => $item['summary'] ?? null,
                        'source_url' => $normalizedUrl,
                        'event_fingerprint' => $eventFingerprint,
                        'priority' => max(0, min(100, (int) $source->trust_score)),
                    ],
                );

                $count++;
            }

            return $count;
        });

        $source->forceFill([
            'status' => 'active',
            'last_fetched_at' => now(),
            'last_http_status' => 200,
            'last_error' => null,
        ])->save();

        AuditEvent::query()->create([
            'event_type' => 'topic_source_collected',
            'payload' => [
                'topic_source_id' => $source->id,
                'source_type' => $source->type,
                'item_count' => $count,
                'collected_at' => now()->toIso8601String(),
            ],
        ]);

        return $count;
    }

    private function connector(TopicSource $source): TopicSourceConnector
    {
        return match (strtolower($source->type)) {
            'rss', 'atom' => new RssTopicSourceConnector,
            'json', 'json_api', 'api' => new JsonApiTopicSourceConnector,
            'html' => new HtmlTopicSourceConnector,
            default => throw new MalformedTopicSourceException("Unsupported topic source type [{$source->type}]."),
        };
    }

    private function handleRequestFailure(TopicSource $source, RequestException $exception): int
    {
        $status = $exception->response?->status();
        $message = $status === null
            ? 'Topic source request failed.'
            : "Topic source request returned HTTP {$status}.";

        if ($status === 401) {
            $this->recordFailure($source, 'Topic source credentials were rejected (HTTP 401).', 'paused', $status);

            return 0;
        }

        $this->recordFailure($source, $message, null, $status);
        if ($status === 429 || ($status !== null && $status >= 500)) {
            throw new TopicSourceException($message, true, $exception);
        }

        return 0;
    }

    private function recordFailure(TopicSource $source, string $message, ?string $status = null, ?int $httpStatus = null): void
    {
        $source->forceFill(array_filter([
            'status' => $status,
            'last_fetched_at' => now(),
            'last_http_status' => $httpStatus,
            'last_error' => $message,
        ], static fn (mixed $value): bool => $value !== null))->save();

        AuditEvent::query()->create([
            'event_type' => 'topic_source_collection_failed',
            'payload' => [
                'topic_source_id' => $source->id,
                'source_type' => $source->type,
                'http_status' => $httpStatus,
                'error' => $message,
            ],
        ]);
    }
}
