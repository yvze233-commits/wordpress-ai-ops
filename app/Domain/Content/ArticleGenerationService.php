<?php

namespace App\Domain\Content;

use App\Domain\Knowledge\EvidenceSnapshot;
use App\Domain\Knowledge\KnowledgeRetrievalService;
use App\Domain\Media\ContentImageRenderer;
use App\Domain\Media\ContentItemDraft;
use App\Domain\Media\ImageMatchingService;
use App\Models\ContentItem;
use App\Models\LibraryImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ArticleGenerationService
{
    public function __construct(
        private readonly StructuredAiGateway $ai,
        private readonly ArticlePromptBuilder $prompts,
        private readonly KnowledgeRetrievalService $knowledge,
        private readonly ImageMatchingService $images,
        private readonly ContentImageRenderer $renderer,
    ) {}

    /**
     * Apply an operator's manual edit (title, excerpt, markdown) without any AI call:
     * re-renders HTML, re-matches images against the new text and refreshes the image plan.
     */
    public function rerender(ContentItem $item, string $title, ?string $excerpt, string $markdown): ContentItem
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            throw new InvalidArgumentException('Article content cannot be empty.');
        }

        $category = (string) ($item->generation_meta['category'] ?? '');
        $scene = (string) ($item->topicCandidate?->summary ?? '');
        $draft = ContentItemDraft::fromText($title, $markdown, $category, $scene);
        $plan = $this->images->match($draft, LibraryImage::query()->where('enabled', true)->get());
        $html = $this->renderer->render(Str::markdown($markdown), $plan, LibraryImage::query()->whereIn('id', $plan->imageIds())->get());

        $item->forceFill([
            'title' => trim($title),
            'slug' => Str::slug($title) ?: (string) $item->slug,
            'excerpt' => filled($excerpt) ? trim($excerpt) : $item->excerpt,
            'content_html' => $html,
            'generation_meta' => [...($item->generation_meta ?? []), 'image_plan' => $plan->placements(), 'content_markdown' => $markdown, 'edited_at' => now()->toIso8601String()],
        ])->save();
        $this->images->persist($item, $plan);

        return $item->fresh();
    }

    public function generate(ContentItem $item): ContentItem
    {
        $evidence = $this->evidence($item);
        $writingSkill = $item->writing_skill_snapshot ?? [];
        $imagePlan = $this->initialImagePlan($item);
        $prompt = $this->prompts->generation($item, $evidence->toArray(), $imagePlan, $writingSkill);
        $result = $this->ai->generate($prompt, ArticlePromptBuilder::generationSchema());
        $this->assertGenerationOutput($result);

        $markdown = trim((string) $result['content_markdown']);
        $html = Str::markdown($markdown);
        $draft = ContentItemDraft::fromText((string) $result['title'], $markdown, (string) ($result['category'] ?? ''), (string) ($item->topicCandidate?->summary ?? ''));
        $matchedPlan = $this->images->match($draft, LibraryImage::query()->where('enabled', true)->get());
        $renderedHtml = $this->renderer->render($html, $matchedPlan, LibraryImage::query()->whereIn('id', $matchedPlan->imageIds())->get());

        return DB::transaction(function () use ($item, $result, $renderedHtml, $evidence, $writingSkill, $matchedPlan, $prompt, $markdown): ContentItem {
            $item->forceFill([
                'title' => trim((string) $result['title']),
                'slug' => trim((string) $result['slug']) ?: Str::slug((string) $result['title']),
                'excerpt' => trim((string) $result['excerpt']),
                'content_html' => $renderedHtml,
                'evidence_snapshot' => $evidence->toArray(),
                'writing_skill_snapshot' => $writingSkill,
                'generation_meta' => [
                    'keywords' => array_values(array_filter((array) $result['keywords'], 'is_string')),
                    'category' => (string) $result['category'],
                    'source_links' => array_values(array_filter((array) $result['source_links'], 'is_string')),
                    'claims' => array_values(array_filter((array) $result['claims'], 'is_string')),
                    'image_plan' => $matchedPlan->placements(),
                    'content_markdown' => $markdown,
                    'prompt_hash' => hash('sha256', $prompt),
                    'generated_at' => now()->toIso8601String(),
                ],
            ])->save();
            $this->images->persist($item, $matchedPlan);

            return $item->fresh();
        });
    }

    public function revise(ContentItem $item, array $instructions): ContentItem
    {
        $prompt = $this->prompts->generation(
            $item,
            $item->evidence_snapshot ?? [],
            $item->generation_meta['image_plan'] ?? [],
            $item->writing_skill_snapshot ?? [],
        )."\n\nREVISION_INSTRUCTIONS:\n".json_encode($instructions, JSON_UNESCAPED_UNICODE);
        $result = $this->ai->generate($prompt, ArticlePromptBuilder::generationSchema());
        $this->assertGenerationOutput($result);
        $markdown = trim((string) $result['content_markdown']);
        $draft = ContentItemDraft::fromText((string) $result['title'], $markdown, (string) ($result['category'] ?? ''), (string) ($item->topicCandidate?->summary ?? ''));
        $plan = $this->images->match($draft, LibraryImage::query()->where('enabled', true)->get());
        $html = $this->renderer->render(Str::markdown($markdown), $plan, LibraryImage::query()->whereIn('id', $plan->imageIds())->get());

        $item->forceFill([
            'title' => trim((string) $result['title']),
            'slug' => trim((string) $result['slug']) ?: Str::slug((string) $result['title']),
            'excerpt' => trim((string) $result['excerpt']),
            'content_html' => $html,
            'generation_meta' => [...($item->generation_meta ?? []), 'image_plan' => $plan->placements(), 'content_markdown' => $markdown, 'revision_at' => now()->toIso8601String()],
        ])->save();
        $this->images->persist($item, $plan);

        return $item->fresh();
    }

    private function evidence(ContentItem $item): EvidenceSnapshot
    {
        if (is_array($item->evidence_snapshot) && isset($item->evidence_snapshot['evidence'])) {
            $snapshot = new EvidenceSnapshot($item->evidence_snapshot['evidence'], (string) ($item->evidence_snapshot['retrieved_at'] ?? now()->toIso8601String()));
            $snapshot->validate();

            return $snapshot;
        }

        $ids = array_values(array_filter((array) ($item->generation_meta['knowledge_base_ids'] ?? config('content-ops.default_knowledge_base_ids', [])), 'is_numeric'));

        return $this->knowledge->retrieve($item->title.' '.($item->topicCandidate?->summary ?? ''), array_map('intval', $ids));
    }

    /** @return list<array<string,mixed>> */
    private function initialImagePlan(ContentItem $item): array
    {
        $draft = ContentItemDraft::fromText($item->title, (string) ($item->topicCandidate?->summary ?? $item->title), null, $item->topicCandidate?->summary);
        $plan = $this->images->match($draft, LibraryImage::query()->where('enabled', true)->get());

        return $plan->placements();
    }

    /** @param array<string,mixed> $result */
    private function assertGenerationOutput(array $result): void
    {
        foreach (ArticlePromptBuilder::generationSchema()['required'] as $key) {
            if (! array_key_exists($key, $result)) {
                throw new InvalidArgumentException("Generation output is missing [{$key}].");
            }
        }
        foreach (['keywords', 'source_links', 'claims'] as $key) {
            if (! is_array($result[$key])) {
                throw new InvalidArgumentException("Generation output [{$key}] must be an array.");
            }
        }
        foreach (['title', 'slug', 'excerpt', 'content_markdown', 'category'] as $key) {
            if (! is_string($result[$key])) {
                throw new InvalidArgumentException("Generation output [{$key}] must be a string.");
            }
        }
    }
}
