<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Prompt;

use HelgeSverre\Synapse\State\Message;

interface PromptInterface
{
    /**
     * Render the prompt with the given values.
     *
     * @param  array<string, mixed>  $values
     * @return string|list<Message>
     */
    public function render(array $values = []): string|array;

    public function getType(): PromptType;
}
