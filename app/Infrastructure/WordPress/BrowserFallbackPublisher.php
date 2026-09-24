<?php

namespace App\Infrastructure\WordPress;

use App\Models\ContentItem;
use App\Models\WordPressConnection;
use RuntimeException;

final class BrowserFallbackPublisher
{
    /** @return array<string,mixed> */
    public function publish(ContentItem $item, WordPressConnection $connection): array
    {
        if (! config('content-ops.playwright_enabled', false)) {
            return ['status' => 'disabled', 'reason' => 'PLAYWRIGHT_ENABLED is false'];
        }

        throw new RuntimeException('Playwright browser fallback is enabled but no runner is configured.');
    }
}
