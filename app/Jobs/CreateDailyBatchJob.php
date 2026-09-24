<?php

namespace App\Jobs;

use App\Domain\Topics\TopicSelectionService;
use App\Models\ContentBatch;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Queue\SerializesModels;

class CreateDailyBatchJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $runDate) {}

    public function handle(?TopicSelectionService $selection = null): ContentBatch
    {
        $selection ??= app(TopicSelectionService::class);
        $runDate = Carbon::parse($this->runDate)->toDateString();
        $batch = ContentBatch::query()->whereDate('run_date', $runDate)->first();
        if ($batch === null) {
            try {
                $batch = ContentBatch::query()->create([
                    'run_date' => $runDate,
                    'target_count' => (int) config('content-ops.topic_daily_target', 4),
                    'status' => 'planned',
                ]);
            } catch (QueryException $exception) {
                if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw $exception;
                }

                $batch = ContentBatch::query()->whereDate('run_date', $runDate)->firstOrFail();
            }
        }
        $existing = $batch->dailySelections()->count();
        $target = (int) $batch->target_count;

        if ($existing < $target) {
            $selection->selectForBatch($batch, $target - $existing);
        }

        $selected = $batch->dailySelections()->count();
        $batch->forceFill([
            'completed_count' => $selected,
            'status' => $selected >= $target ? 'completed' : 'shortfall',
        ])->save();

        return $batch->fresh();
    }
}
