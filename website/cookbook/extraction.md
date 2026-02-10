# Data Extraction

Extract structured data from unstructured text using the JSON parser with schema validation.

## The Pattern

1. Define the expected output schema as JSON Schema
2. Instruct the LLM to extract data matching that schema
3. Parse the response with the `json` parser

## Example: Contact Extraction

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage(
        'You are a data extraction assistant. Extract the requested information ' .
        'from the provided text. Always respond with valid JSON.'
    )
    ->addUserMessage(
        'Extract the contact information from this text: "{{text}}"',
        parseTemplate: true,
    );

$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'company' => ['type' => 'string'],
        ],
        'required' => ['name'],
    ],
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([
    'text' => 'Hi, I\'m Jane Doe from Acme Corp. You can reach me at ' .
              'jane@acme.com or call 555-0123.',
]);

$contact = $result->getValue();
// [
//     'name' => 'Jane Doe',
//     'email' => 'jane@acme.com',
//     'phone' => '555-0123',
//     'company' => 'Acme Corp',
// ]
```

## Example: Event Extraction

```php
$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'events' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'date' => ['type' => 'string'],
                        'location' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
]);

$result = $executor->execute([
    'text' => 'The PHP conference is on March 15th in Amsterdam. ' .
              'There\'s also a Laravel meetup on April 2nd in London.',
]);
// {events: [{title: "PHP conference", date: "March 15th", location: "Amsterdam"}, ...]}
```

## Tips

- Be specific in the system prompt about the expected output format
- Use JSON Schema `required` fields for mandatory data
- Include `description` in schema properties to guide the LLM
- For complex nested structures, provide an example in the system prompt
