<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Prompt\Template\Template;
use LlmExe\Provider\Response\GenerationResponse;

/**
 * Parses the response and replaces template variables in it.
 * Useful for post-processing LLM output with additional context.
 *
 * @extends BaseParser<string>
 */
final class ReplaceStringTemplateParser extends BaseParser
{
    /** @var array<string, mixed> */
    private array $replacements = [];

    /** @var array<string, callable(mixed): string> */
    private array $helpers = [];

    public function __construct(
        private readonly bool $strict = false,
    ) {
        parent::__construct();
    }

    /** @param array<string, mixed> $replacements */
    public function withReplacements(array $replacements): self
    {
        $clone = clone $this;
        $clone->replacements = array_merge($clone->replacements, $replacements);

        return $clone;
    }

    public function setReplacement(string $key, mixed $value): self
    {
        $this->replacements[$key] = $value;

        return $this;
    }

    /** @param callable(mixed): string $helper */
    public function registerHelper(string $name, callable $helper): self
    {
        $this->helpers[$name] = $helper;

        return $this;
    }

    public function parse(GenerationResponse $response): string
    {
        $text = trim($this->getTextContent($response));

        $template = new Template($text, $this->strict);

        foreach ($this->helpers as $name => $helper) {
            $template->registerHelper($name, $helper);
        }

        return $template->render($this->replacements);
    }

    /** @return array<string, mixed> */
    public function getReplacements(): array
    {
        return $this->replacements;
    }
}
