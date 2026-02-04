<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Streaming;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Parser for Server-Sent Events (SSE) streams.
 *
 * SSE format:
 *   event: <event-type>
 *   data: <json-data>
 *   <blank line>
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events
 */
final class SseParser
{
    /**
     * Parse SSE lines into structured events.
     *
     * @param  iterable<string>  $lines  Raw lines from the stream
     * @return Generator<array{event: ?string, data: string}>
     */
    public static function parse(iterable $lines): Generator
    {
        $event = null;
        $data = [];

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");

            // Blank line = end of event
            if ($line === '') {
                if ($data !== []) {
                    yield [
                        'event' => $event,
                        'data' => implode("\n", $data),
                    ];
                    $event = null;
                    $data = [];
                }

                continue;
            }

            // Comment line (starts with :)
            if (str_starts_with($line, ':')) {
                continue;
            }

            // Parse field: value
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $field = substr($line, 0, $colonPos);
                $value = substr($line, $colonPos + 1);

                // Remove single leading space if present (SSE spec)
                if (str_starts_with($value, ' ')) {
                    $value = substr($value, 1);
                }
            } else {
                // Field with no value
                $field = $line;
                $value = '';
            }

            match ($field) {
                'event' => $event = $value,
                'data' => $data[] = $value,
                'id', 'retry' => null, // Ignored for now
                default => null, // Unknown fields ignored per spec
            };
        }

        // Yield any remaining event
        if ($data !== []) {
            yield [
                'event' => $event,
                'data' => implode("\n", $data),
            ];
        }
    }

    /**
     * Read lines from a PSR-7 stream, yielding each line as it's read.
     *
     * @return Generator<string>
     */
    public static function readLines(StreamInterface $stream, ?StreamContext $ctx = null): Generator
    {
        $buffer = '';

        while (! $stream->eof()) {
            // Check for cancellation
            if ($ctx?->shouldCancel()) {
                return;
            }

            // Read one byte at a time for true streaming
            $byte = $stream->read(1);

            if ($byte === '') {
                continue;
            }

            $buffer .= $byte;

            // Yield complete lines
            if ($byte === "\n") {
                yield $buffer;
                $buffer = '';
            }
        }

        // Yield any remaining content
        if ($buffer !== '') {
            yield $buffer;
        }
    }

    /**
     * Parse a PSR-7 stream as SSE events.
     *
     * @return Generator<array{event: ?string, data: string}>
     */
    public static function parseStream(StreamInterface $stream, ?StreamContext $ctx = null): Generator
    {
        yield from self::parse(self::readLines($stream, $ctx));
    }
}
