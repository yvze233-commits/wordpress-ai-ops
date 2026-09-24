<?php

namespace App\Domain\Content;

final class IdempotencyKey
{
    public static function forTopic(int $topicCandidateId, string $contentVersion, int $wordpressConnectionId): string
    {
        return hash('sha256', implode(':', [$topicCandidateId, $contentVersion, $wordpressConnectionId]));
    }

    public static function make(int $topicCandidateId, string $contentVersion, int $wordpressConnectionId): string
    {
        return self::forTopic($topicCandidateId, $contentVersion, $wordpressConnectionId);
    }
}
