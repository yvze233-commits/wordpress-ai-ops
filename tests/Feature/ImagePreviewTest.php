<?php

namespace Tests\Feature;

use App\Domain\Media\ContentImageRenderer;
use App\Domain\Media\ImagePlacementPlan;
use App\Models\ImageLibrary;
use App\Domain\Media\ImageIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_image_preview_returns_an_image_and_article_html_uses_preview_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('content-images/sample.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $library = ImageLibrary::create(['name' => '预览库']);
        $image = app(ImageIngestionService::class)->create($library, 'content-images/sample.png', 'image/png', ['alt_text' => '示例图片']);

        $this->get(route('media.images.show', $image))->assertOk()->assertHeader('Content-Type', 'image/png');
        $plan = new ImagePlacementPlan([['image_id' => $image->id, 'position' => 0, 'paragraph_index' => null, 'confidence' => 1, 'reason' => '测试']]);
        $html = app(ContentImageRenderer::class)->render('<p>正文</p>', $plan, [$image]);
        $this->assertStringContainsString('/media/images/'.$image->id, $html);
        $this->assertStringContainsString('alt="示例图片"', $html);
    }
}
