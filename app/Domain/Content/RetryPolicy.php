<?php

namespace App\Domain\Content;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

final class RetryPolicy
{
    public function shouldRetry(Throwable|int|string $failure): bool
    {
        if (is_int($failure)) {
            return $failure === 408 || $failure === 409 || $failure === 425 || $failure === 429 || $failure >= 500;
        }
        if ($failure instanceof ConnectionException) {
            return true;
        }
        if ($failure instanceof RequestException) {
            return $this->shouldRetry($failure->response->status());
        }
        if ($failure instanceof Throwable) {
            return str_contains(strtolower($failure->getMessage()), 'timeout')
                || str_contains(strtolower($failure->getMessage()), 'temporar');
        }

        return in_array(strtolower($failure), ['timeout', 'rate_limit', 'temporary'], true);
    }

    public function backoff(int $attempt): int
    {
        return min(900, 10 * (2 ** max(0, $attempt - 1)));
    }
}
