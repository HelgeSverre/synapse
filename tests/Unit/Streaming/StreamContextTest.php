<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Streaming;

use LlmExe\Streaming\StreamContext;
use PHPUnit\Framework\TestCase;

final class StreamContextTest extends TestCase
{
    public function test_default_not_cancelled(): void
    {
        $ctx = new StreamContext;

        $this->assertFalse($ctx->shouldCancel());
    }

    public function test_with_cancellation_callback_false(): void
    {
        $ctx = new StreamContext(
            isCancelled: fn () => false,
        );

        $this->assertFalse($ctx->shouldCancel());
    }

    public function test_with_cancellation_callback_true(): void
    {
        $ctx = new StreamContext(
            isCancelled: fn () => true,
        );

        $this->assertTrue($ctx->shouldCancel());
    }

    public function test_dynamic_cancellation(): void
    {
        $cancelled = false;

        $ctx = new StreamContext(
            isCancelled: function () use (&$cancelled) {
                return $cancelled;
            },
        );

        $this->assertFalse($ctx->shouldCancel());

        $cancelled = true;

        $this->assertTrue($ctx->shouldCancel());
    }

    public function test_with_timeout(): void
    {
        $ctx = new StreamContext(
            timeout: 30.0,
        );

        $this->assertSame(30.0, $ctx->timeout);
    }

    public function test_with_connection_abort_check(): void
    {
        $ctx = StreamContext::withConnectionAbortCheck();

        // In CLI context, connection_aborted() returns 0
        $this->assertFalse($ctx->shouldCancel());
    }
}
