<?php

namespace App\Domain\Skills;

use App\Models\Skill;
use Illuminate\Support\Facades\DB;

final class SkillCatalog
{
    /** @return list<array{slug:string,name:string,kind:string,raw_text:string,pass_threshold:?int}> */
    public static function builtIns(): array
    {
        $writing = [
            ['slug' => 'hot_news', 'name' => '热点资讯写作', 'kind' => 'writing'],
            ['slug' => 'education_explainer', 'name' => '教育科普写作', 'kind' => 'writing'],
            ['slug' => 'brand_event', 'name' => '品牌事件写作', 'kind' => 'writing'],
        ];
        $reviews = [
            ['slug' => 'facts', 'name' => '事实准确性审核'],
            ['slug' => 'source_completeness', 'name' => '来源完整性审核'],
            ['slug' => 'brand_voice', 'name' => '品牌口径审核'],
            ['slug' => 'structure', 'name' => '文章结构审核'],
            ['slug' => 'sensitive_content', 'name' => '敏感内容审核'],
            ['slug' => 'image_fit', 'name' => '图片匹配审核'],
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
                'raw_text' => '# '.$skill['name']."\n\n遵守知识库证据和来源边界，输出结构化结果。",
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
}
