<?php

namespace App\Domain\Content;

final class ContentState
{
    public const CANDIDATE = 'candidate';

    public const LOCKED = 'locked';

    public const GENERATING = 'generating';

    public const AWAITING_REVIEW = 'awaiting_review';

    public const NEEDS_MANUAL_REVIEW = 'needs_manual_review';

    public const APPROVED = 'approved';

    public const RETRYABLE_FAILED = 'retryable_failed';

    public const PERMANENTLY_FAILED = 'permanently_failed';

    public const WP_DRAFT_WRITTEN = 'wp_draft_written';

    public const PUBLISHED = 'published';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::CANDIDATE,
            self::LOCKED,
            self::GENERATING,
            self::AWAITING_REVIEW,
            self::NEEDS_MANUAL_REVIEW,
            self::APPROVED,
            self::RETRYABLE_FAILED,
            self::PERMANENTLY_FAILED,
            self::WP_DRAFT_WRITTEN,
            self::PUBLISHED,
        ];
    }

    public static function isKnown(string $state): bool
    {
        return in_array($state, self::values(), true);
    }
}
