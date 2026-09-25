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
        $item->loadMissing(['batch.task', 'imagePlacements.image']);
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

        $configuredThreshold = $item->batch?->task?->review_pass_threshold;
        $threshold = max(0, min(100, (int) ($configuredThreshold ?? ($plan['pass_threshold'] ?? $result['threshold'] ?? config('content-ops.review_pass_threshold', 70)))));
        $result['threshold'] = $threshold;
        $result['passed'] = (bool) $result['passed'] && (int) $result['score'] >= $threshold
            && $result['conflicts'] === [] && $result['missing_evidence'] === [] && $result['image_issues'] === [];

        $result = $this->applyTaskRequirements($item, $result);

        $result['review_stages'] = count($stageResults);

        return $result;
    }

    /** @param array<string,mixed> $result */
    private function applyTaskRequirements(ContentItem $item, array $result): array
    {
        $requirements = is_array($item->review_skill_snapshot['requirements'] ?? null) ? $item->review_skill_snapshot['requirements'] : [];
        $settings = is_array($item->batch?->task?->settings) ? $item->batch->task->settings : [];
        $requiresImages = (bool) ($requirements['require_images'] ?? $settings['require_images'] ?? false);
        $requiresLinks = (bool) ($requirements['require_source_links'] ?? $settings['require_source_links'] ?? false);

        if ($requiresImages) {
            $placements = $item->imagePlacements->filter(fn ($placement): bool => $placement->image !== null);
            $missingAlt = $placements->contains(fn ($placement): bool => trim((string) ($placement->image?->alt_text ?? '')) === '');
            $hasImageTag = preg_match('/<img\b[^>]*>/i', (string) $item->content_html) === 1;
            if ($placements->isEmpty() || ! $hasImageTag) {
                $result['image_issues'][] = '任务要求至少插入一张图片，但当前文章没有匹配到图片。';
                $result['revision_instructions'][] = '从启用的图片库中选择与正文语义匹配的图片，并插入文章正文。';
            } elseif ($missingAlt) {
                $result['image_issues'][] = '任务要求所有图片都有 alt，但存在空 alt。';
                $result['revision_instructions'][] = '为每张图片补充准确、简洁的中文 alt。';
            }
        }

        if ($requiresLinks) {
            $sourceLinks = array_values(array_filter((array) ($item->generation_meta['source_links'] ?? []), 'is_string'));
            $hasHref = preg_match('/<a\\b[^>]*\\bhref\\s*=\\s*["\\\'][^"\\\']+["\\\']/i', (string) $item->content_html) === 1;
            if ($sourceLinks === [] || ! $hasHref) {
                $result['missing_evidence'][] = '任务要求正文包含来源链接，但当前文章没有可核验的来源链接。';
                $result['revision_instructions'][] = '在相关结论后加入来源链接，并用一句话说明链接对应的证据。';
            }
        }

        $result['image_issues'] = array_values(array_unique(array_filter((array) ($result['image_issues'] ?? []), 'is_string')));
        $result['missing_evidence'] = array_values(array_unique(array_filter((array) ($result['missing_evidence'] ?? []), 'is_string')));
        $result['revision_instructions'] = array_values(array_unique(array_filter((array) ($result['revision_instructions'] ?? []), 'is_string')));
        $result['passed'] = (bool) $result['passed'] && $result['image_issues'] === [] && $result['missing_evidence'] === [];

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
