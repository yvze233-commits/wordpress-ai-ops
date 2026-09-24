<?php

namespace App\Domain\Content;

use App\Models\ContentItem;
use Carbon\CarbonInterface;

/**
 * Assigns a publish slot to an approved article according to the publishing strategy:
 * a random time inside the configured daily window, honouring the daily publish cap
 * (when the cap is reached, the slot rolls over to the next day's window).
 */
final class PublishScheduler
{
    /**
     * @param  'draft'|'publish'  $remoteStatus
     */
    public function schedule(ContentItem $item, string $remoteStatus): void
    {
        $when = $this->nextSlot();
        $item->forceFill(['publish_status' => $remoteStatus, 'scheduled_publish_at' => $when])->save();
        $item->auditEvents()->create([
            'event_type' => 'publish_scheduled',
            'payload' => [
                'scheduled_publish_at' => $when->toIso8601String(),
                'remote_status' => $remoteStatus,
            ],
        ]);
    }

    public function nextSlot(): CarbonInterface
    {
        $now = now();
        $start = (string) config('content-ops.publish_window_start', '08:00');
        $end = (string) config('content-ops.publish_window_end', '22:00');
        $dailyMax = (int) config('content-ops.publish_daily_max', 0);

        $todayWindow = $this->window($now, $start, $end);
        if ($dailyMax > 0 && $this->publishedToday() >= $dailyMax) {
            $tomorrow = $this->window($now->copy()->addDay(), $start, $end);

            return $this->randomIn($tomorrow['start'], $tomorrow['end']);
        }

        $earliest = $now->copy()->addMinute();
        if ($earliest > $todayWindow['end']) {
            $tomorrow = $this->window($now->copy()->addDay(), $start, $end);

            return $this->randomIn($tomorrow['start'], $tomorrow['end']);
        }

        return $this->randomIn($todayWindow['start']->max($earliest), $todayWindow['end']);
    }

    /** @return array{start:CarbonInterface, end:CarbonInterface} */
    private function window(CarbonInterface $day, string $start, string $end): array
    {
        $startAt = $day->copy()->setTimeFromTimeString($start);
        $endAt = $day->copy()->setTimeFromTimeString($end);
        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $day->copy()->endOfDay();
        }

        return ['start' => $startAt, 'end' => $endAt];
    }

    private function randomIn(CarbonInterface $start, ?CarbonInterface $end = null): CarbonInterface
    {
        $end ??= $start->copy()->addMinute();
        $seconds = max(0, $end->diffInSeconds($start));

        return $start->copy()->addSeconds(random_int(0, $seconds));
    }

    private function publishedToday(): int
    {
        return ContentItem::query()
            ->whereIn('state', [ContentState::WP_DRAFT_WRITTEN, ContentState::PUBLISHED])
            ->whereDate('updated_at', now()->toDateString())
            ->count();
    }
}
