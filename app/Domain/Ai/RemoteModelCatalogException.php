<?php

namespace App\Domain\Ai;

use RuntimeException;

final class RemoteModelCatalogException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 502,
        public readonly string $reason = 'model_fetch_failed',
    ) {
        parent::__construct($message);
    }
}
