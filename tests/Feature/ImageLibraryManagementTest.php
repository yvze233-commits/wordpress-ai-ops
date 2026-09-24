<?php

namespace Tests\Feature;

use App\Models\ImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageLibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function pngFile(string $name = 'classroom.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) base64_decode(self::PNG_BASE64, true));
    }

    public function test_libraries_can_be_created_listed_and_deleted(): void
    {
        $this->post('/admin/images', ['name' => '教育配图', 'description' => '产品相关图片'])
            ->assertRedirect('/admin/images')
            ->assertSessionHas('status');

        $library = ImageLibrary::query()->where('name', '教育配图')->firstOrFail();

        $this->get('/admin/images')->assertOk()->assertSee('教育配图');
        $this->get('/admin/images/'.$library->id)->assertOk()->assertSee('上传图片')->assertSee('配图匹配测试');

        $this->post('/admin/images/'.$library->id.'/delete')->assertRedirect('/admin/images');
        $this->assertNull($library->fresh());
    }

    public function test_images_can_be_batch_uploaded_with_shared_metadata(): void
    {
        $library = ImageLibrary::create(['name' => '上传测试']);

        $this->post('/admin/images/'.$library->id.'/images', [
            'images' => [$this->pngFile('one.png'), $this->pngFile('two.png')],
            'caption' => '在线课堂',
            'keywords' => '在线教育,课堂',
            'notes' => '适用于介绍课程模式的段落',
            'copyright_state' => 'owned',
        ])->assertRedirect('/admin/images/'.$library->id)->assertSessionHas('status');

        $this->assertSame(2, $library->images()->count());
        $image = $library->images()->first();
        $this->assertSame(['在线教育', '课堂'], $image->keywords);
        $this->assertSame('owned', $image->copyright_state);
        $this->assertNotNull($image->width);
    }

    public function test_unsupported_format_and_oversize_report_readable_errors(): void
    {
        $library = ImageLibrary::create(['name' => '校验测试']);

        $pdf = UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 fake');
        $this->post('/admin/images/'.$library->id.'/images', ['images' => [$pdf]])
            ->assertSessionHasErrors();

        $oversize = UploadedFile::fake()->createWithContent('huge.png', (string) base64_decode(self::PNG_BASE64, true))->size(6000);
        $this->post('/admin/images/'.$library->id.'/images', ['images' => [$oversize]])
            ->assertSessionHasErrors();

        $this->assertSame(0, $library->images()->count());
    }

    public function test_image_metadata_can_be_edited_toggled_and_deleted(): void
    {
        $library = ImageLibrary::create(['name' => '编辑测试']);
        $image = app(\App\Domain\Media\ImageIngestionService::class)->ingestFile($library, $this->pngFile(), [
            'caption' => '原始图注',
            'copyright_state' => 'unknown',
        ]);

        $this->post('/admin/images/'.$library->id.'/images/'.$image->id.'/update', [
            'caption' => '老师与学生在教室',
            'alt_text' => '课堂场景',
            'notes' => '适合教学方法相关段落',
            'keywords' => '老师,课堂,教学',
            'scenes' => '课程介绍',
            'copyright_state' => 'licensed',
        ])->assertRedirect('/admin/images/'.$library->id);

        $image->refresh();
        $this->assertSame(['老师', '课堂', '教学'], $image->keywords);
        $this->assertSame('licensed', $image->copyright_state);
        $this->assertSame('课堂场景', $image->alt_text);

        $this->post('/admin/images/'.$library->id.'/images/'.$image->id.'/toggle')->assertRedirect();
        $this->assertFalse($image->fresh()->enabled);

        $this->post('/admin/images/'.$library->id.'/images/'.$image->id.'/delete')->assertRedirect();
        $this->assertNull($image->fresh());
    }

    public function test_uploaded_image_can_be_served_for_preview(): void
    {
        $library = ImageLibrary::create(['name' => '预览测试']);
        $image = app(\App\Domain\Media\ImageIngestionService::class)->ingestFile($library, $this->pngFile());

        $this->get('/admin/image-files/'.$image->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_match_test_returns_ranked_placements_without_persisting(): void
    {
        $library = ImageLibrary::create(['name' => '匹配测试']);
        app(\App\Domain\Media\ImageIngestionService::class)->ingestFile($library, $this->pngFile('edu.png'), [
            'caption' => '在线课堂',
            'keywords' => '在线教育,课堂教学',
            'copyright_state' => 'licensed',
        ]);

        $this->postJson('/admin/images/'.$library->id.'/match-test', [
            'title' => '在线教育的课堂变革',
            'article_text' => "## 背景\n\n课堂教学正在转向在线教育平台。\n\n## 未来\n\n更多机构加入在线教育浪潮。",
        ])->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.placements.0.role', '特色图');

        $this->assertSame(0, \DB::table('image_placements')->count());
    }

    public function test_match_test_without_relevant_images_returns_empty_plan(): void
    {
        $library = ImageLibrary::create(['name' => '空匹配']);
        app(\App\Domain\Media\ImageIngestionService::class)->ingestFile($library, $this->pngFile(), [
            'keywords' => '厨房,烹饪',
        ]);

        $this->postJson('/admin/images/'.$library->id.'/match-test', [
            'title' => '在线教育的课堂变革',
            'article_text' => '课堂教学正在转向在线教育平台。',
        ])->assertOk()->assertJsonPath('data.count', 0);
    }
}
