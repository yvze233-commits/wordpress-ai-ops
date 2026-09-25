<?php

namespace App\Domain\Content;

use App\Domain\Knowledge\EvidenceSnapshot;
use App\Domain\Media\ImagePlacementPlan;
use App\Domain\Skills\SkillExecutionSnapshot;
use App\Models\ContentItem;
use App\Models\TopicCandidate;

final class ArticlePromptBuilder
{
    /** @return array<string, mixed> */
    public static function generationSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'slug', 'excerpt', 'content_markdown', 'keywords', 'category', 'source_links', 'claims'],
            'properties' => [
                'title' => ['type' => 'string', 'minLength' => 1],
                'slug' => ['type' => 'string', 'minLength' => 1],
                'excerpt' => ['type' => 'string'],
                'content_markdown' => ['type' => 'string', 'minLength' => 1],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'category' => ['type' => 'string'],
                'source_links' => ['type' => 'array', 'items' => ['type' => 'string']],
                'claims' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function generationOutputSchema(): array
    {
        return self::generationSchema();
    }

    /** @return array<string,mixed> */
    public static function topicTitleSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['titles'],
            'properties' => [
                'titles' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'required' => ['title', 'rationale', 'score'],
                        'properties' => [
                            'title' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 80],
                            'rationale' => ['type' => 'string'],
                            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function topicTitles(array $topic, array $keywords = []): string
    {
        return $this->withEnvelope(
            '基于给定时讯生成 3 个中文文章标题候选。标题必须忠实于原始事件，不得把评论、预测或推测写成既成事实；不得添加原始资料没有支持的人名、数字、时间或结论。每个标题给出简短理由和 0-100 分质量评分，只输出 topicTitleSchema 结构化 JSON。',
            ['topic' => $topic, 'task_keywords' => array_values($keywords)],
        );
    }

    /** @return array<string, mixed> */
    public static function reviewSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['passed', 'score', 'threshold', 'criterion_scores', 'conflicts', 'missing_evidence', 'image_issues', 'revision_instructions'],
            'properties' => [
                'passed' => ['type' => 'boolean'],
                'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'threshold' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'criterion_scores' => ['type' => 'object'],
                'conflicts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'missing_evidence' => ['type' => 'array', 'items' => ['type' => 'string']],
                'image_issues' => ['type' => 'array', 'items' => ['type' => 'string']],
                'revision_instructions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function reviewOutputSchema(): array
    {
        return self::reviewSchema();
    }

    public function generation(ContentItem $item, array|EvidenceSnapshot $evidence, array|ImagePlacementPlan $imagePlan, array|SkillExecutionSnapshot $writingSkill): string
    {
        $candidate = $item->topicCandidate;
        $payload = [
            'candidate' => $candidate instanceof TopicCandidate ? [
                'title' => $candidate->title,
                'summary' => $candidate->summary,
                'source_url' => $candidate->source_url,
                'source_type' => $candidate->source_type,
            ] : ['title' => $item->title],
            'evidence_snapshot' => $evidence instanceof EvidenceSnapshot ? $evidence->toArray() : $evidence,
            'image_placement_plan' => $imagePlan instanceof ImagePlacementPlan ? $imagePlan->placements() : $imagePlan,
            'writing_skill_snapshot' => $writingSkill instanceof SkillExecutionSnapshot ? $writingSkill->toArray() : $writingSkill,
        ];

        return $this->withEnvelope(
            '生成一篇可发布到 WordPress 草稿的中文文章。严格使用给定证据和来源；证据没有支持的事实不要扩展，不要复制来源原文。正文必须自然包含至少一个与主题直接相关的 Markdown 超链接，链接目标只能来自 source_links 或证据中的 source_url，并配有解释链接用途的文字。不要在正文暴露“证据为空、无法生成、内部审核”等系统元信息。根据图片计划在适合段落使用图片。输出必须符合 generation schema。',
            $payload,
        );
    }

    public function buildGenerationPrompt(ContentItem $item, array|EvidenceSnapshot $evidence, array|ImagePlacementPlan $imagePlan, array|SkillExecutionSnapshot $writingSkill): string
    {
        return $this->generation($item, $evidence, $imagePlan, $writingSkill);
    }

    public function review(ContentItem $item, array|SkillExecutionSnapshot $reviewSkill): string
    {
        return $this->withEnvelope(
            '审核下面的文章草稿。按审核 Skill 逐项检查事实、证据、来源、敏感内容、结构和图片适配。发现冲突、来源缺失或图片不确定时必须列出。图片可能来自本地素材库，发布 WordPress 草稿时系统会先上传媒体并替换为公开 URL；不要仅因当前 src 是本地路径判定失败，应重点检查 alt、图片语义、版权状态和与段落的匹配。只输出符合 review schema 的结构化结果。',
            [
                'draft' => [
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'excerpt' => $item->excerpt,
                    'content_html' => $item->content_html,
                    'generation_meta' => $item->generation_meta,
                ],
                'evidence_snapshot' => $item->evidence_snapshot,
            'review_skill_snapshot' => $reviewSkill instanceof SkillExecutionSnapshot ? $reviewSkill->toArray() : $reviewSkill,
            'task_requirements' => $this->taskRequirements($item),
            ],
        );
    }

    public function buildReviewPrompt(ContentItem $item, array|SkillExecutionSnapshot $reviewSkill): string
    {
        return $this->review($item, $reviewSkill instanceof SkillExecutionSnapshot ? $reviewSkill->toArray() : $reviewSkill);
    }

    private function withEnvelope(string $instruction, array $payload): string
    {
        return $instruction."\n\nINPUT_JSON:\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** @return array{require_images:bool,require_source_links:bool} */
    private function taskRequirements(ContentItem $item): array
    {
        $snapshot = is_array($item->review_skill_snapshot) ? $item->review_skill_snapshot : [];
        $requirements = is_array($snapshot['requirements'] ?? null) ? $snapshot['requirements'] : [];

        return [
            'require_images' => (bool) ($requirements['require_images'] ?? false),
            'require_source_links' => (bool) ($requirements['require_source_links'] ?? false),
        ];
    }
}
