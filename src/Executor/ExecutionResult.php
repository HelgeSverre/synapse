<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\ConversationState;

/**
 * @template T
 */
final readonly class ExecutionResult
{
    /**
     * @param  T  $value
     */
    public function __construct(
        public mixed $value,
        public ConversationState $state,
        public GenerationResponse $response,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    /** @return T */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
