<?php

namespace Tests\Unit;

use App\Domain\Media\ContentItemDraft;
use App\Domain\Media\ImageMatchingService;
use App\Models\ImageLibrary;
use App\Models\LibraryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_medium_and_long_drafts_get_bounded_non_repeating_images(): void
    {
        $library = ImageLibrary::create(['name' => '标注图库']);
        $images = collect([
            ['path' => '/education.jpg', 'notes' => '教育 课程 学习', 'keywords' => ['教育', '课程'], 'copyright_state' => 'licensed'],
            ['path' => '/analytics.jpg', 'notes' => '学习数据分析 报表', 'keywords' => ['数据分析'], 'copyright_state' => 'licensed'],
            ['path' => '/classroom.jpg', 'notes' => '课堂 教师 学生', 'keywords' => ['课堂'], 'copyright_state' => 'approved'],
            ['path' => '/campus.jpg', 'notes' => '校园 场景', 'keywords' => ['校园'], 'copyright_state' => 'approved'],
            ['path' => '/future.jpg', 'notes' => '人工智能 教育趋势', 'keywords' => ['人工智能'], 'copyright_state' => 'licensed'],
            ['path' => '/policy.jpg', 'notes' => '教育政策 规划', 'keywords' => ['政策'], 'copyright_state' => 'approved'],
        ])->map(fn (array $attributes): LibraryImage => $library->images()->create([
            ...$attributes,
            'mime_type' => 'image/jpeg',
            'enabled' => true,
        ]));
        $service = new ImageMatchingService;

        $short = $service->match(new ContentItemDraft('教育课程', [
            ['heading' => null, 'content' => '教育课程介绍。'],
        ]), $images);
        $medium = $service->match(new ContentItemDraft('教育课程数据', array_map(
            fn (string $heading): array => ['heading' => $heading, 'content' => $heading.' 相关内容。'],
            ['课程', '数据分析', '课堂', '校园', '趋势'],
        )), $images);
        $long = $service->match(new ContentItemDraft('人工智能教育', array_map(
            fn (string $heading): array => ['heading' => $heading, 'content' => $heading.' 详细内容。'],
            ['课程', '数据分析', '课堂', '校园', '趋势', '政策', '学习', '教育'],
        )), $images);

        $this->assertSame(1, $short->count());
        $this->assertGreaterThanOrEqual(2, $medium->count());
        $this->assertLessThanOrEqual(3, $medium->count());
        $this->assertLessThanOrEqual(5, $long->count());
        $this->assertCount(count(array_unique($long->imageIds())), $long->imageIds());
        $this->assertContains('featured', array_column($long->placements(), 'role'));
    }

    public function test_images_below_confidence_are_omitted(): void
    {
        $library = ImageLibrary::create(['name' => '低匹配图库']);
        $image = $library->images()->create([
            'path' => '/unrelated.jpg', 'mime_type' => 'image/jpeg', 'notes' => '完全无关主题', 'enabled' => true,
        ]);

        $plan = (new ImageMatchingService)->match(
            new ContentItemDraft('人工智能教育', [['content' => '课程与学习数据。']]),
            collect([$image]),
        );

        $this->assertSame(0, $plan->count());
    }
}
