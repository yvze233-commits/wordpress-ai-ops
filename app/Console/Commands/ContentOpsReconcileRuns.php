<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileContentRunJob;
use App\Models\ContentRun;
use App\Models\TopicCandidate;
use Illuminate\Console\Command;

class ContentOpsReconcileRuns extends Command
{
    protected $signature = 'content-ops:reconcile-runs {--age=900 : Running run age in seconds}';

    protected $description = 'Queue reconciliation for interrupted content runs and release expired topic locks.';

    public function handle(): int
    {
        $cutoff = now()->subSeconds(max(60, (int) $this->option('age')));
        $runs = ContentRun::query()->where('status', 'running')->where('updated_at', '<', $cutoff)->pluck('id');
        foreach ($runs as $runId) {
            ReconcileContentRunJob::dispatch((int) $runId);
        }
        $released = TopicCandidate::query()->where('status', 'locked')->whereNotNull('locked_until')->where('locked_until', '<', now())->update(['status' => 'candidate', 'locked_until' => null]);

        $this->info("Queued {$runs->count()} run(s) and released {$released} expired lock(s).");

        return self::SUCCESS;
    }
}
