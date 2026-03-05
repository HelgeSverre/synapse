<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Agent;

use Generator;
use HelgeSverre\Synapse\Executor\ExecutionResult;
use HelgeSverre\Synapse\Executor\StreamingResult;
use HelgeSverre\Synapse\Streaming\StreamContext;

interface AgentInterface
{
    /**
     * @param  array<string, mixed>  $input
     * @param  list<\HelgeSverre\Synapse\State\Message>  $history
     */
    public function run(array $input = [], array $history = []): ExecutionResult|StreamingResult;

    /**
     * @param  array<string, mixed>  $input
     * @param  list<\HelgeSverre\Synapse\State\Message>  $history
     * @return Generator<\HelgeSverre\Synapse\Streaming\StreamEvent>
     */
    public function stream(array $input = [], array $history = [], ?StreamContext $ctx = null): Generator;
}
