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
        $result = $this->ai->review($this->prompts->review($item, $item->review_skill_snapshot ?? []), ArticlePromptBuilder::reviewSchema());
        $this->assertReviewOutput($result);

        $threshold = max(0, min(100, (int) ($result['threshold'] ?: $item->review_skill_snapshot['pass_threshold'] ?? config('content-ops.review_pass_threshold', 70))));
        $result['threshold'] = $threshold;
        $result['passed'] = (bool) $result['passed'] && (int) $result['score'] >= $threshold
            && $result['conflicts'] === [] && $result['missing_evidence'] === [] && $result['image_issues'] === [];

        return $result;
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
