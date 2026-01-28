<?php

declare(strict_types=1);

namespace LlmExe\Hooks;

interface HookDispatcherInterface
{
    public function dispatch(object $event): void;

    /** @param callable(object): void $listener */
    public function addListener(string $eventClass, callable $listener): void;

    public function removeListener(string $eventClass, callable $listener): void;

    public function clearListeners(?string $eventClass = null): void;
}
