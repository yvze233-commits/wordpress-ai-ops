<?php

namespace App\Domain\Topics;

final class MalformedTopicSourceException extends TopicSourceException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, false, $previous);
    }
}
