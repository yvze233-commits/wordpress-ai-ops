<?php

namespace App\Domain\Topics;

use App\Models\ContentItem;
use App\Models\TopicCandidate;
use Illuminate\Support\Collection;

final class DuplicateDetector
{
    /** @var list<string> */
    private const SUCCESSFUL_STATES = ['approved', 'wp_draft_written', 'published'];

    public function isDuplicate(TopicCandidate $candidate, ?Collection $selected = null): bool
    {
        $selected ??= collect();

        foreach ($selected as $other) {
            if ($other instanceof TopicCandidate && $other->isNot($candidate) && $this->matches($candidate, $other)) {
                return true;
            }
        }

        $successful = ContentItem::query()
            ->with('topicCandidate')
            ->whereIn('state', self::SUCCESSFUL_STATES)
            ->latest('id')
            ->limit(100)
            ->get()
            ->pluck('topicCandidate')
            ->filter();

        foreach ($successful as $other) {
            if ($other instanceof TopicCandidate && $this->matches($candidate, $other)) {
                return true;
            }
        }

        return false;
    }

    public function matches(TopicCandidate $first, TopicCandidate $second): bool
    {
        if ($first->normalized_title !== '' && $first->normalized_title === $second->normalized_title) {
            return true;
        }

        if ($first->source_url !== null && $first->source_url !== '' && $first->source_url === $second->source_url) {
            return true;
        }

        if ($first->event_fingerprint !== null && $first->event_fingerprint !== '' && $first->event_fingerprint === $second->event_fingerprint) {
            return true;
        }

        if ($first->normalized_title === '' || $second->normalized_title === '') {
            return false;
        }

        if ($this->hasDistinctNumericSuffix($first->normalized_title, $second->normalized_title)) {
            return false;
        }

        similar_text($first->normalized_title, $second->normalized_title, $percent);

        return $percent >= 82.0;
    }

    private function hasDistinctNumericSuffix(string $first, string $second): bool
    {
        if (preg_match('/^(.*?)(\d+)$/u', $first, $firstMatch) !== 1
            || preg_match('/^(.*?)(\d+)$/u', $second, $secondMatch) !== 1) {
            return false;
        }

        return $firstMatch[1] === $secondMatch[1] && $firstMatch[2] !== $secondMatch[2];
    }
}
