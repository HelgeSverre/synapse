<?php

declare(strict_types=1);

namespace LlmExe\Prompt\Template;

final class Template
{
    /** @var array<string, callable(mixed): string> */
    private array $helpers = [];

    /** @var array<string, string> */
    private array $partials = [];

    public function __construct(
        private readonly string $template,
        private readonly bool $strict = false,
    ) {}

    /** @param callable(mixed): string $helper */
    public function registerHelper(string $name, callable $helper): self
    {
        $this->helpers[$name] = $helper;

        return $this;
    }

    public function registerPartial(string $name, string $template): self
    {
        $this->partials[$name] = $template;

        return $this;
    }

    /** @param array<string, mixed> $values */
    public function render(array $values): string
    {
        $result = $this->template;

        // Render partials: {{> PartialName}}
        $result = preg_replace_callback(
            '/\{\{>\s*(\w+)\s*\}\}/',
            fn (array $m) => $this->partials[$m[1]] ?? ($this->strict ? throw new \InvalidArgumentException("Unknown partial: {$m[1]}") : ''),
            $result,
        ) ?? $result;

        // Render helpers: {{helperName arg}}
        $result = preg_replace_callback(
            '/\{\{(\w+)\s+([^}]+)\}\}/',
            function (array $matches) use ($values): string {
                $helper = $matches[1];
                $arg = trim($matches[2]);

                if (! isset($this->helpers[$helper])) {
                    return $matches[0]; // Not a helper, leave as-is for variable replacement
                }

                // Resolve arg from values if it's a variable name
                $argValue = $this->resolveValue($arg, $values);

                return ($this->helpers[$helper])($argValue);
            },
            $result,
        ) ?? $result;

        // Render variables: {{variable}} or {{nested.path}}
        $result = preg_replace_callback(
            '/\{\{(\w+(?:\.\w+)*)\}\}/',
            function (array $matches) use ($values): string {
                $value = $this->resolveValue($matches[1], $values);

                if ($value === null) {
                    if ($this->strict) {
                        throw new \InvalidArgumentException("Missing template variable: {$matches[1]}");
                    }

                    return '';
                }

                return $this->stringify($value);
            },
            $result,
        ) ?? $result;

        return $result;
    }

    private function resolveValue(string $path, array $values): mixed
    {
        $keys = explode('.', $path);
        $current = $values;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } elseif (is_object($current) && property_exists($current, $key)) {
                $current = $current->{$key};
            } else {
                return null;
            }
        }

        return $current;
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }
}
