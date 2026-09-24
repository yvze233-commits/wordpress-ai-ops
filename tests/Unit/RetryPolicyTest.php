<?php

namespace Tests\Unit;

use App\Domain\Content\RetryPolicy;
use Tests\TestCase;

class RetryPolicyTest extends TestCase
{
    public function test_transient_statuses_retry_but_credentials_and_permissions_do_not(): void
    {
        $policy = new RetryPolicy;
        $this->assertTrue($policy->shouldRetry(429));
        $this->assertTrue($policy->shouldRetry(503));
        $this->assertFalse($policy->shouldRetry(401));
        $this->assertFalse($policy->shouldRetry(403));
        $this->assertSame(40, $policy->backoff(3));
    }
}
