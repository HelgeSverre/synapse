<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks;

interface HookDispatcherInterface
{
    public function dispatch(object $event): void;

    /**
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $eventClass
     * @param  callable(TEvent): void  $listener
     */
    public function addListener(string $eventClass, callable $listener): void;

    /**
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $eventClass
     * @param  callable(TEvent): void  $listener
     */
    public function removeListener(string $eventClass, callable $listener): void;

    public function clearListeners(?string $eventClass = null): void;
}
