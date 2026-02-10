# Template Engine

Synapse's template engine uses Handlebars-style <code v-pre>{{variable}}</code> syntax for dynamic content in prompts.

## Variable Substitution

Basic variable replacement:

```php
$prompt = createChatPrompt()
    ->addSystemMessage('You are an expert on {{topic}}.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$prompt->render(['topic' => 'PHP', 'question' => 'What is PSR-18?']);
```

### Nested Paths

Access nested values with dot notation:

```php
$prompt->addUserMessage('Hello {{user.name}}!', parseTemplate: true);

$prompt->render(['user' => ['name' => 'Alice']]);
// "Hello Alice!"
```

## Helpers

Register custom helper functions:

```php
$prompt = createChatPrompt();
$prompt->registerHelper('upper', fn(string $value) => strtoupper($value));
$prompt->registerHelper('lower', fn(string $value) => strtolower($value));

$prompt->addUserMessage('{{upper name}} - {{lower email}}', parseTemplate: true);

$prompt->render(['name' => 'alice', 'email' => 'ALICE@EXAMPLE.COM']);
// "ALICE - alice@example.com"
```

Helper syntax: <code v-pre>{{helperName argument}}</code>

## Partials

Register reusable template snippets:

```php
$prompt = createChatPrompt();
$prompt->registerPartial('greeting', 'Hello, {{name}}! Welcome to {{app}}.');

$prompt->addUserMessage('{{> greeting}}', parseTemplate: true);

$prompt->render(['name' => 'Alice', 'app' => 'Synapse']);
// "Hello, Alice! Welcome to Synapse."
```

Partial syntax: <code v-pre>{{> partialName}}</code>

## Strict Mode

By default, missing variables are replaced with empty strings. Enable strict mode to throw an exception instead:

```php
$prompt = createChatPrompt();
$prompt->strict(true);

$prompt->addUserMessage('Hello {{name}}!', parseTemplate: true);

// This throws because 'name' is not provided:
$prompt->render([]); // throws RuntimeException
```

Strict mode is useful for catching missing variables during development.

## parseTemplate Parameter

The `parseTemplate` parameter controls whether template variables are processed:

```php
// Templates parsed (variables replaced)
$prompt->addUserMessage('Hello {{name}}', parseTemplate: true);

// Templates NOT parsed (literal text sent to LLM)
$prompt->addUserMessage('Use {{curly braces}} for variables', parseTemplate: false);
```

System messages always have templates parsed. User messages default to `parseTemplate: false`.

## Complete Example

```php
$prompt = createChatPrompt();

// Register helpers
$prompt->registerHelper('upper', fn($s) => strtoupper($s));
$prompt->registerHelper('json', fn($v) => json_encode($v, JSON_PRETTY_PRINT));

// Register partials
$prompt->registerPartial('format_instructions',
    'Respond with valid JSON matching this schema: {{json schema}}'
);

// Strict mode
$prompt->strict(true);

// Build prompt
$prompt
    ->addSystemMessage('You are a {{upper role}} assistant. {{> format_instructions}}')
    ->addUserMessage('Process this: {{input}}', parseTemplate: true);

$prompt->render([
    'role' => 'data extraction',
    'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
    'input' => 'John Smith, age 34',
]);
```
