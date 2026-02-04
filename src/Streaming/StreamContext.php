<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Streaming;

use Closure;

/**
 * Context for controlling stream behavior (cancellation, timeout).
 */
final readonly class StreamContext
{
    /**
     * @param  Closure(): bool|null  $isCancelled  Callback that returns true if stream should stop
     * @param  float|null  $timeout  Maximum time in seconds before timeout
     */
    public function __construct(
        public ?Closure $isCancelled = null,
        public ?float $timeout = null,
    ) {}

    /**
     * Check if the stream has been cancelled.
     */
    public function shouldCancel(): bool
    {
        if ($this->isCancelled === null) {
            return false;
        }

        return ($this->isCancelled)();
    }

    /**
     * Create a context that cancels when the connection is aborted (for web requests).
     */
    public static function withConnectionAbortCheck(): self
    {
        return new self(
            isCancelled: static fn (): bool => connection_aborted() === 1,
        );
    }
}
