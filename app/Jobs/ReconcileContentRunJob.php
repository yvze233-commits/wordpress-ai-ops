<?php

namespace App\Jobs;

use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Models\ContentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileContentRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $contentRunId) {}

    public function handle(): void
    {
        $run = ContentRun::query()->with('contentItem')->find($this->contentRunId);
        if ($run === null || $run->status !== 'running') {
            return;
        }
        $item = $run->contentItem;
        $run->forceFill(['status' => 'reconciled', 'error_message' => 'Run was stale and reconciled after process interruption.', 'payload' => ['reconciled_at' => now()->toIso8601String()]])->save();
        if ($item !== null && ContentStateTransition::canTransition((string) $item->state, ContentState::RETRYABLE_FAILED)) {
            $item->forceFill(['state' => ContentState::RETRYABLE_FAILED])->save();
            $item->auditEvents()->create(['event_type' => 'content_run_reconciled', 'payload' => ['run_id' => $run->id, 'stage' => $run->stage]]);
        }
    }
}
