# CoreExecutor

Wraps a plain PHP function as an executor. Useful for non-LLM steps in pipelines or for testing.

## Usage

```php
use function HelgeSverre\Synapse\createCoreExecutor;

$calculator = createCoreExecutor(fn($input) => $input['a'] + $input['b']);

$result = $calculator->execute(['a' => 5, 'b' => 3]);
echo $result->getValue(); // 8
```

## Constructor

```php
new CoreExecutor(
    handler: fn(array $input) => $input['a'] + $input['b'],
    name: 'calculator',    // ?string
    hooks: null,           // ?HookDispatcherInterface
    state: null,           // ?ConversationState
);
```

The `handler` callable receives the input array and can return any value.

## Examples

### Data transformation

```php
$normalizer = createCoreExecutor(fn($input) => [
    'name' => trim($input['name']),
    'email' => strtolower($input['email']),
]);

$result = $normalizer->execute([
    'name' => '  John Smith  ',
    'email' => 'JOHN@Example.COM',
]);
// ['name' => 'John Smith', 'email' => 'john@example.com']
```

### Validation

```php
$validator = createCoreExecutor(function ($input) {
    $errors = [];
    if (empty($input['email'])) {
        $errors[] = 'Email is required';
    }
    return ['valid' => empty($errors), 'errors' => $errors];
});
```

### Chaining with LLM executors

```php
// Step 1: Clean data
$clean = createCoreExecutor(fn($input) => [
    'text' => strip_tags($input['html']),
]);

// Step 2: Summarize with LLM
$summarize = createLlmExecutor([...]);

// Run in sequence
$cleaned = $clean->execute(['html' => $rawHtml]);
$summary = $summarize->execute(['text' => $cleaned->getValue()['text']]);
```
