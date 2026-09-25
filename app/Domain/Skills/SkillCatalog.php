<?php

namespace App\Domain\Skills;

use App\Models\Skill;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

final class SkillCatalog
{
    public const DEFAULT_WRITING_SLUG = 'geoflow_impression_article';

    /** @var list<string> */
    public const DEFAULT_REVIEW_STAGE_SLUGS = ['geoflow_review_facts', 'geoflow_review_publish'];

    /** @return list<array<string,mixed>> */
    public static function builtIns(): array
    {
        $writing = [
            ['slug' => 'geoflow_impression_article', 'name' => 'GEOFlow 印象文生成', 'kind' => 'writing', 'instructions' => '用清晰的事实、场景和读者收益建立可信印象。先给直接结论，再用知识库证据解释背景、方法、适用人群和限制。避免夸张宣传，不补写未被证据支持的事实。'],
            ['slug' => 'geoflow_ranking_article', 'name' => 'GEOFlow 榜单文生成', 'kind' => 'writing', 'instructions' => '按 GEOFlow 榜单文章方法生成榜单或选型文章。明确评选范围、时间、维度和权重；每个排名绑定可核验来源；同时写清适用场景、限制、FAQ 和来源清单。资料不足时降级为待核验，不凑榜、不虚构竞品。'],
            ['slug' => 'hot_news', 'name' => '热点资讯写作', 'kind' => 'writing', 'instructions' => '围绕最新事件写出有来源、有时间边界、面向读者的中文文章，不扩展证据之外的事实。'],
            ['slug' => 'education_explainer', 'name' => '教育科普写作', 'kind' => 'writing', 'instructions' => '用通俗语言解释概念、步骤、选择标准和常见误区，保留证据来源和适用边界。'],
            ['slug' => 'brand_event', 'name' => '品牌事件写作', 'kind' => 'writing', 'instructions' => '基于已核验的品牌资料和事件来源写作，品牌表达必须服从事实和读者问题。'],
        ];
        $reviews = [
            ['slug' => 'geoflow_review_facts', 'name' => 'GEOFlow 第一段：事实与证据审核', 'instructions' => '第一段只检查事实、来源、证据快照、时间边界和数字口径。发现冲突、来源缺失或无法核验的断言必须列出，不得用文风判断代替证据判断。'],
            ['slug' => 'geoflow_review_publish', 'name' => 'GEOFlow 第二段：结构与发布风险审核', 'instructions' => '第二段只检查文章结构、读者可读性、敏感内容、品牌表达、图片适配和 WordPress 发布风险。给出可执行的修改指令。'],
            ['slug' => 'facts', 'name' => '事实准确性审核', 'instructions' => '逐项核对文章事实和知识库证据，不通过未核验断言。'],
            ['slug' => 'source_completeness', 'name' => '来源完整性审核', 'instructions' => '检查关键结论是否有可追溯来源，缺失时列出。'],
            ['slug' => 'brand_voice', 'name' => '品牌口径审核', 'instructions' => '检查品牌表达是否符合已提供的品牌资料和边界。'],
            ['slug' => 'structure', 'name' => '文章结构审核', 'instructions' => '检查标题、摘要、小标题、段落、FAQ 和结论是否完整易读。'],
            ['slug' => 'sensitive_content', 'name' => '敏感内容审核', 'instructions' => '检查法律、医疗、金融、安全和夸大承诺等风险表达。'],
            ['slug' => 'image_fit', 'name' => '图片匹配审核', 'instructions' => '检查文章中的图片是否与段落语义、备注和来源一致。'],
        ];
        foreach ($reviews as &$review) {
            $review['kind'] = 'review';
        }
        unset($review);

        return collect([...$writing, ...$reviews])->map(function (array $skill): array {
            $required = $skill['kind'] === 'writing'
                ? ['title', 'slug', 'excerpt', 'content_markdown', 'keywords', 'category', 'source_links', 'claims']
                : ['passed', 'score', 'threshold', 'criterion_scores', 'conflicts', 'missing_evidence', 'image_issues', 'revision_instructions'];

            return [
                ...$skill,
                'raw_text' => '# '.$skill['name']."\n\n".($skill['instructions'] ?? '遵守知识库证据和来源边界，输出结构化结果。'),
                'pass_threshold' => $skill['kind'] === 'review' ? 70 : null,
                'output_schema' => ['type' => 'object', 'required' => $required],
            ];
        })->all();
    }

    public function seedBuiltIns(): void
    {
        foreach (self::builtIns() as $definition) {
            DB::transaction(function () use ($definition): void {
                $skill = Skill::query()->firstOrCreate(
                    ['slug' => $definition['slug']],
                    ['name' => $definition['name'], 'kind' => $definition['kind'], 'source' => 'built_in', 'enabled' => true, 'current_version' => 1],
                );
                if ($skill->versions()->where('version', 1)->exists()) {
                    return;
                }
                $skill->versions()->create([
                    'version' => 1,
                    'raw_text' => $definition['raw_text'],
                    'parsed_rules' => ['kind' => $definition['kind'], 'sections' => ['instructions' => $definition['raw_text']]],
                    'output_schema' => $definition['output_schema'],
                    'prohibited_terms' => [],
                    'pass_threshold' => $definition['pass_threshold'],
                    'validation_report' => ['valid' => true, 'errors' => [], 'warnings' => []],
                    'content_hash' => hash('sha256', $definition['raw_text']),
                ]);
            });
        }
    }

    /** @return array{writing:array<string,mixed>,review:array<string,mixed>} */
    public function snapshotsForNewContent(): array
    {
        $this->seedBuiltIns();
        $writingSlug = (string) SystemSetting::value('writing_skill_slug', self::DEFAULT_WRITING_SLUG);
        $writing = $this->snapshot($writingSlug, 'writing');
        $review = array_map(fn (string $slug): array => $this->snapshot($slug, 'review'), self::DEFAULT_REVIEW_STAGE_SLUGS);

        return [
            'writing' => $writing,
            'review' => [
                'strategy' => 'geoflow_two_pass',
                'pass_threshold' => 70,
                'stages' => $review,
            ],
        ];
    }

    /** @return array{writing:array<string,mixed>,review:array<string,mixed>} */
    public function snapshotsForConfiguration(string $writingSlug, string $reviewSlug = 'geoflow_two_pass'): array
    {
        $this->seedBuiltIns();
        $writing = $this->snapshot($writingSlug, 'writing');
        $reviewStages = $reviewSlug === 'geoflow_two_pass'
            ? self::DEFAULT_REVIEW_STAGE_SLUGS
            : [$reviewSlug];
        $review = array_map(fn (string $slug): array => $this->snapshot($slug, 'review'), $reviewStages);

        return ['writing' => $writing, 'review' => ['strategy' => $reviewSlug, 'pass_threshold' => 70, 'stages' => $review]];
    }

    /** @return array<string,mixed> */
    private function snapshot(string $slug, string $kind): array
    {
        $skill = Skill::query()->where('slug', $slug)->where('kind', $kind)->where('enabled', true)->first();
        if ($skill === null) {
            $skill = Skill::query()->where('kind', $kind)->where('enabled', true)->firstOrFail();
        }
        $version = $skill->currentVersion();
        if ($version === null) {
            throw new \RuntimeException("Skill [{$slug}] has no current version.");
        }

        return SkillExecutionSnapshot::fromVersion($version)->toArray();
    }
}
