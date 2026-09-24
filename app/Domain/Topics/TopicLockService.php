<?php

namespace App\Domain\Topics;

use App\Models\TopicCandidate;
use Illuminate\Support\Facades\DB;

final class TopicLockService
{
    public function acquire(TopicCandidate|int $candidate, int $ttlSeconds = 900): ?TopicCandidate
    {
        $candidateId = $candidate instanceof TopicCandidate ? $candidate->getKey() : $candidate;

        return DB::transaction(function () use ($candidateId, $ttlSeconds): ?TopicCandidate {
            $locked = TopicCandidate::query()->lockForUpdate()->find($candidateId);
            if ($locked === null) {
                return null;
            }

            $available = $locked->status === 'candidate'
                || ($locked->status === 'locked' && $locked->locked_until?->isPast());
            if (! $available) {
                return null;
            }

            $locked->forceFill([
                'status' => 'locked',
                'locked_until' => now()->addSeconds($ttlSeconds),
            ])->save();

            return $locked->fresh();
        });
    }

    public function release(TopicCandidate|int $candidate): void
    {
        $candidateId = $candidate instanceof TopicCandidate ? $candidate->getKey() : $candidate;

        TopicCandidate::query()
            ->whereKey($candidateId)
            ->where('status', 'locked')
            ->update(['status' => 'candidate', 'locked_until' => null]);
    }
}
