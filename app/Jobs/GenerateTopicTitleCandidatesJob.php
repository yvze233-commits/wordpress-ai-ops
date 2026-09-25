<?php

namespace App\Jobs;

use App\Domain\Topics\TopicTitleCandidateService;
use App\Models\ContentTask;
use App\Models\TopicCandidate;
use App\Models\AuditEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateTopicTitleCandidatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $topicCandidateId, public readonly ?int $taskId = null) {}

    public function handle(TopicTitleCandidateService $service): void
    {
        $candidate = TopicCandidate::query()->findOrFail($this->topicCandidateId);
        $task = $this->taskId === null ? null : ContentTask::query()->find($this->taskId);
        try {
            $titles = $service->generate($candidate, $task);
        } catch (Throwable $exception) {
            $this->failed($exception);
            return;
        }
        $best = collect($titles)->sortByDesc('score')->first();
        if ($best !== null) {
            $candidate->contentItems()->whereIn('state', ['locked', 'generating'])->update(['title' => $best->title]);
        }
        AuditEvent::query()->create(['event_type' => 'topic_title_candidates_generated', 'payload' => ['topic_candidate_id' => $candidate->id, 'task_id' => $task?->id, 'count' => count($titles)]]);
    }

    public function failed(?Throwable $exception): void
    {
        AuditEvent::query()->create(['event_type' => 'topic_title_candidates_failed', 'payload' => ['topic_candidate_id' => $this->topicCandidateId, 'task_id' => $this->taskId, 'error' => $exception?->getMessage()]]);
    }
}
