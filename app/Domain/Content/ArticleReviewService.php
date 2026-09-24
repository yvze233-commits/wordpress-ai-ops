<?php

namespace App\Domain\Content;

use App\Models\ContentItem;
use InvalidArgumentException;

final class ArticleReviewService
{
    public function __construct(
        private readonly StructuredAiGateway $ai,
        private readonly ArticlePromptBuilder $prompts,
    ) {}

    /** @return array<string,mixed> */
    public function review(ContentItem $item): array
    {
        $plan = $item->review_skill_snapshot ?? [];
        $stages = is_array($plan['stages'] ?? null) && $plan['stages'] !== [] ? $plan['stages'] : [$plan];
        $stageResults = [];
        foreach (array_values($stages) as $index => $stage) {
            $stage = is_array($stage) ? $stage : [];
            $stage['review_pass'] = $index + 1;
            $stage['review_pass_count'] = count($stages);
            $result = $this->ai->review($this->prompts->review($item, $stage), ArticlePromptBuilder::reviewSchema());
            $this->assertReviewOutput($result);
            $stageResults[] = $result;
        }

        $result = $this->combineStageResults($stageResults);

        $threshold = max(0, min(100, (int) ($result['threshold'] ?: $plan['pass_threshold'] ?? config('content-ops.review_pass_threshold', 70))));
        $result['threshold'] = $threshold;
        $result['passed'] = (bool) $result['passed'] && (int) $result['score'] >= $threshold
            && $result['conflicts'] === [] && $result['missing_evidence'] === [] && $result['image_issues'] === [];

        $result['review_stages'] = count($stageResults);

        return $result;
    }

    /** @param list<array<string,mixed>> $results */
    private function combineStageResults(array $results): array
    {
        $all = static fn (string $key): array => array_values(array_unique(array_merge(...array_map(fn (array $result): array => array_values(array_filter((array) ($result[$key] ?? []), 'is_string')), $results))));
        $criterionScores = [];
        foreach ($results as $index => $stage) {
            $criterionScores['pass_'.($index + 1)] = $stage['criterion_scores'] ?? [];
        }

        return [
            'passed' => collect($results)->every(fn (array $result): bool => (bool) $result['passed']),
            'score' => min(array_map(fn (array $result): int => (int) $result['score'], $results)),
            'threshold' => max(array_map(fn (array $result): int => (int) $result['threshold'], $results)),
            'criterion_scores' => $criterionScores,
            'conflicts' => $all('conflicts'),
            'missing_evidence' => $all('missing_evidence'),
            'image_issues' => $all('image_issues'),
            'revision_instructions' => $all('revision_instructions'),
        ];
    }

    /** @param array<string,mixed> $result */
    private function assertReviewOutput(array $result): void
    {
        foreach (ArticlePromptBuilder::reviewSchema()['required'] as $key) {
            if (! array_key_exists($key, $result)) {
                throw new InvalidArgumentException("Review output is missing [{$key}].");
            }
        }
        if (! is_bool($result['passed']) || ! is_numeric($result['score']) || ! is_numeric($result['threshold'])) {
            throw new InvalidArgumentException('Review output has invalid score fields.');
        }
        foreach (['conflicts', 'missing_evidence', 'image_issues', 'revision_instructions'] as $key) {
            if (! is_array($result[$key])) {
                throw new InvalidArgumentException("Review output [{$key}] must be an array.");
            }
        }
        if (! is_array($result['criterion_scores'])) {
            throw new InvalidArgumentException('Review output [criterion_scores] must be an object.');
        }
    }
}
