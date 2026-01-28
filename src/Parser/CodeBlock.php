<?php

declare(strict_types=1);

namespace LlmExe\Parser;

/**
 * Represents a parsed code block with language and code.
 */
final readonly class CodeBlock
{
    public function __construct(
        public ?string $language,
        public string $code,
    ) {}

    /** @return array{language: string|null, code: string} */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'code' => $this->code,
        ];
    }
}
