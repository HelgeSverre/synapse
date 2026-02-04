<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks;

final class HookDispatcher implements HookDispatcherInterface
{
    /** @var array<string, list<callable(object): void>> */
    private array $listeners = [];

    /** @var array<string, list<callable(object): void>> */
    private array $onceListeners = [];

    public function dispatch(object $event): void
    {
        $eventClass = $event::class;

        // Regular listeners
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener($event);
        }

        // Once listeners (fire once then remove)
        if (isset($this->onceListeners[$eventClass])) {
            foreach ($this->onceListeners[$eventClass] as $listener) {
                $listener($event);
            }
            unset($this->onceListeners[$eventClass]);
        }
    }

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass] ??= [];
        $this->listeners[$eventClass][] = $listener;
    }

    /** @param callable(object): void $listener */
    public function once(string $eventClass, callable $listener): void
    {
        $this->onceListeners[$eventClass] ??= [];
        $this->onceListeners[$eventClass][] = $listener;
    }

    public function removeListener(string $eventClass, callable $listener): void
    {
        if (! isset($this->listeners[$eventClass])) {
            return;
        }

        $this->listeners[$eventClass] = array_values(
            array_filter(
                $this->listeners[$eventClass],
                fn (callable $l): bool => $l !== $listener,
            ),
        );
    }

    public function clearListeners(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            $this->listeners = [];
            $this->onceListeners = [];
        } else {
            unset($this->listeners[$eventClass], $this->onceListeners[$eventClass]);
        }
    }

    public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]) && count($this->listeners[$eventClass]) > 0;
    }
}
