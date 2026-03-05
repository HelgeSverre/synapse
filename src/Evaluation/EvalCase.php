<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

final readonly class EvalCase
{
    /** @var (\Closure(mixed, mixed): bool)|null */
    public ?\Closure $matcher;

    /**
     * @param  array<string, mixed>  $input
     * @param  callable(mixed, mixed): bool|null  $matcher
     */
    public function __construct(
        public string $name,
        public array $input,
        public bool $hasExpected = false,
        public mixed $expected = null,
        public bool $useSnapshot = false,
        public ?string $snapshotKey = null,
        ?callable $matcher = null,
    ) {
        $this->matcher = $matcher !== null ? \Closure::fromCallable($matcher) : null;

        if (! $this->hasExpected && ! $this->useSnapshot) {
            throw new \InvalidArgumentException('EvalCase must define expected value or enable snapshot mode.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function expect(string $name, array $input, mixed $expected, ?callable $matcher = null): self
    {
        return new self(
            name: $name,
            input: $input,
            hasExpected: true,
            expected: $expected,
            useSnapshot: false,
            snapshotKey: null,
            matcher: $matcher,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function snapshot(string $name, array $input, ?string $snapshotKey = null, ?callable $matcher = null): self
    {
        return new self(
            name: $name,
            input: $input,
            hasExpected: false,
            expected: null,
            useSnapshot: true,
            snapshotKey: $snapshotKey,
            matcher: $matcher,
        );
    }
}
