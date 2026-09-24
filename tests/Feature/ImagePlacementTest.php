<?php

namespace Tests\Feature;

use App\Domain\Media\ContentImageRenderer;
use App\Domain\Media\ContentItemDraft;
use App\Domain\Media\ImageIngestionService;
use App\Domain\Media\ImageMatchingService;
use App\Models\ContentItem;
use App\Models\ImageLibrary;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ImagePlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_annotated_image_notes_are_preserved_and_rendered_with_traceable_metadata(): void
    {
        $library = ImageLibrary::create(['name' => '运营图片']);
        $image = app(ImageIngestionService::class)->create($library, '/assets/course.jpg', 'image/jpeg', [
            'notes' => '课程封面，适合课程介绍段落',
            'caption' => '课程介绍',
            'alt_text' => '课程封面图',
            'keywords' => '课程,教育',
            'copyright_state' => 'licensed',
        ]);
        $this->assertSame('课程封面，适合课程介绍段落', $image->notes);
        $draft = new ContentItemDraft('课程介绍', [
            ['heading' => '课程内容', 'content' => '课程介绍与学习安排。'],
            ['heading' => '学习方式', 'content' => '学习方式说明。'],
            ['heading' => '常见问题', 'content' => '课程问题解答。'],
            ['heading' => '报名流程', 'content' => '报名流程说明。'],
        ]);
        $plan = app(ImageMatchingService::class)->match($draft, collect([$image]));
        $html = app(ContentImageRenderer::class)->render('<p>课程介绍与学习安排。</p><p>学习方式说明。</p>', $plan, [$image]);

        $this->assertStringContainsString('data-content-image-placement="0"', $html);
        $this->assertStringContainsString('alt="课程封面图"', $html);
        $this->assertStringContainsString('<figcaption>课程介绍</figcaption>', $html);

        $candidate = TopicCandidate::create([
            'source_type' => 'title_library',
            'source_key' => 'image-placement-topic',
            'title' => '课程介绍',
            'normalized_title' => '课程介绍',
        ]);
        $content = ContentItem::create([
            'title' => '课程介绍',
            'state' => 'locked',
            'idempotency_key' => 'image-placement-item',
            'topic_candidate_id' => $candidate->id,
        ]);
        app(ImageMatchingService::class)->persist($content, $plan);
        $this->assertDatabaseHas('image_placements', ['content_item_id' => $content->id, 'library_image_id' => $image->id]);
        $this->assertSame(1, $image->fresh()->usage_count);
    }

    public function test_unsupported_mime_types_are_rejected(): void
    {
        $library = ImageLibrary::create(['name' => '安全图片库']);

        $this->expectException(InvalidArgumentException::class);
        app(ImageIngestionService::class)->create($library, '/tmp/script.php', 'application/x-php');
    }
}
