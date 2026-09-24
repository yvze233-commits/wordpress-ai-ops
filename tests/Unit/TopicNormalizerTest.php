<?php

namespace Tests\Unit;

use App\Domain\Topics\TopicFingerprint;
use App\Domain\Topics\TopicNormalizer;
use PHPUnit\Framework\TestCase;

class TopicNormalizerTest extends TestCase
{
    public function test_titles_collapse_whitespace_and_normalize_full_width_punctuation(): void
    {
        $normalizer = new TopicNormalizer;

        $this->assertSame(
            '人工智能,教育升级!',
            $normalizer->title("  人工智能，\n 教育升级！ "),
        );
    }

    public function test_urls_ignore_tracking_parameters_and_trailing_slashes(): void
    {
        $normalizer = new TopicNormalizer;

        $first = $normalizer->url('HTTPS://Example.com/news/?utm_source=rss&id=42&utm_medium=email');
        $second = $normalizer->url('https://example.com/news/?id=42');

        $this->assertSame('https://example.com/news?id=42', $first);
        $this->assertSame($first, $second);
    }

    public function test_equal_normalized_urls_have_equal_fingerprints(): void
    {
        $first = TopicFingerprint::forUrl('https://example.com/news/?utm_campaign=daily&id=7');
        $second = TopicFingerprint::forUrl('https://EXAMPLE.com/news?id=7');

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
        $this->assertNotSame($first, TopicFingerprint::forEvent(['source_key' => 'different-event']));
    }
}
