<?php

namespace App\Domain\Content;

use InvalidArgumentException;

final class ContentStateTransition
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        ContentState::CANDIDATE => [ContentState::LOCKED, ContentState::PERMANENTLY_FAILED],
        ContentState::LOCKED => [ContentState::GENERATING, ContentState::RETRYABLE_FAILED, ContentState::PERMANENTLY_FAILED],
        ContentState::GENERATING => [ContentState::AWAITING_REVIEW, ContentState::RETRYABLE_FAILED, ContentState::PERMANENTLY_FAILED],
        ContentState::AWAITING_REVIEW => [ContentState::APPROVED, ContentState::NEEDS_MANUAL_REVIEW, ContentState::RETRYABLE_FAILED],
        ContentState::NEEDS_MANUAL_REVIEW => [ContentState::APPROVED, ContentState::RETRYABLE_FAILED, ContentState::PERMANENTLY_FAILED],
        ContentState::APPROVED => [ContentState::WP_DRAFT_WRITTEN, ContentState::PUBLISHED, ContentState::RETRYABLE_FAILED, ContentState::PERMANENTLY_FAILED],
        ContentState::RETRYABLE_FAILED => [ContentState::LOCKED, ContentState::GENERATING, ContentState::APPROVED, ContentState::PERMANENTLY_FAILED],
        ContentState::WP_DRAFT_WRITTEN => [ContentState::PUBLISHED],
        ContentState::PUBLISHED => [],
        ContentState::PERMANENTLY_FAILED => [],
    ];

    public static function assertAllowed(string $from, string $to): void
    {
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new InvalidArgumentException("Content state transition from [{$from}] to [{$to}] is not allowed.");
        }
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }
}
