# HookDispatcher

The event dispatcher that manages listeners and dispatches events.

## Usage

Most of the time you use hooks through the executor's `on()` method. But you can also use `HookDispatcher` directly.

## Via Executor

```php
$executor->on(OnSuccess::class, fn($e) => echo "Done!");
$executor->off(OnSuccess::class, $listener);
```

## Standalone

```php
use HelgeSverre\Synapse\Hooks\HookDispatcher;

$dispatcher = new HookDispatcher();

// Add listener
$dispatcher->addListener(OnSuccess::class, fn($e) => echo "Done!");

// One-time listener
$dispatcher->once(OnSuccess::class, fn($e) => echo "First time only!");

// Remove listener
$dispatcher->removeListener(OnSuccess::class, $listener);

// Clear all listeners for an event
$dispatcher->clearListeners(OnSuccess::class);

// Check if listeners exist
$dispatcher->hasListeners(OnSuccess::class); // bool

// Dispatch an event
$dispatcher->dispatch(new OnSuccess($result, $durationMs));
```

## Sharing Hooks

Share a dispatcher across multiple executors:

```php
$hooks = new HookDispatcher();
$hooks->addListener(AfterProviderCall::class, function ($e) {
    echo "[{$e->request->model}] {$e->response->usage->getTotal()} tokens\n";
});

$executor1 = createLlmExecutor([..., 'hooks' => $hooks]);
$executor2 = createLlmExecutor([..., 'hooks' => $hooks]);
```

## Logging Example

```php
$hooks = new HookDispatcher();

$hooks->addListener(BeforeProviderCall::class, function ($e) {
    file_put_contents('llm.log', date('c') . " CALL {$e->request->model}\n", FILE_APPEND);
});

$hooks->addListener(AfterProviderCall::class, function ($e) {
    $tokens = $e->response->usage->getTotal();
    file_put_contents('llm.log', date('c') . " DONE {$tokens} tokens\n", FILE_APPEND);
});

$hooks->addListener(OnError::class, function ($e) {
    file_put_contents('llm.log', date('c') . " ERROR {$e->error->getMessage()}\n", FILE_APPEND);
});
```
