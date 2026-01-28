<?php

declare(strict_types=1);

namespace LlmExe\Prompt;

use LlmExe\Prompt\Template\Template;

abstract class BasePrompt implements PromptInterface
{
    /** @var array<string, callable(mixed): string> */
    protected array $helpers = [];

    /** @var array<string, string> */
    protected array $partials = [];

    protected bool $strict = false;

    /** @param callable(mixed): string $helper */
    public function registerHelper(string $name, callable $helper): static
    {
        $this->helpers[$name] = $helper;

        return $this;
    }

    public function registerPartial(string $name, string $template): static
    {
        $this->partials[$name] = $template;

        return $this;
    }

    public function strict(bool $strict = true): static
    {
        $this->strict = $strict;

        return $this;
    }

    /** @param array<string, mixed> $values */
    protected function renderTemplate(string $template, array $values): string
    {
        $tpl = new Template($template, $this->strict);

        foreach ($this->helpers as $name => $helper) {
            $tpl->registerHelper($name, $helper);
        }

        foreach ($this->partials as $name => $partial) {
            $tpl->registerPartial($name, $partial);
        }

        return $tpl->render($values);
    }
}
