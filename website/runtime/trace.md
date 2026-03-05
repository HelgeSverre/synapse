# TraceContext and Exporters

`HookTraceBridge` turns executor hooks into trace records you can export to logs, metrics pipelines, or OpenTelemetry adapters.

## Quick Example

```php
use function HelgeSverre\Synapse\{
    createExecutor,
    createInMemoryTraceExporter,
    createTraceBridge,
    createTraceContext,
};

$executor = createExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
]);

$exporter = createInMemoryTraceExporter();
$bridge = createTraceBridge(
    exporter: $exporter,
    context: createTraceContext(['service' => 'chat-api']),
);

$bridge->register($executor->getHooks());
$result = $executor->run(['question' => 'hello']);
```

## Exported Span Types

- `executor.run`
- `provider.call`
- `stream.call`
- `tool.call`
- `executor.error`

See `examples/production/trace-bridge.php` for a full runnable example.
