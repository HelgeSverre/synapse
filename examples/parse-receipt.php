#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Receipt Parser Example - Structured Output with JSON Schema
 *
 * This example demonstrates how to:
 * 1. Extract text from a PDF receipt using spatie/pdf-to-text
 * 2. Use OpenAI's structured output (response_format) to parse line items
 * 3. Validate the response against a JSON schema
 *
 * Requirements:
 * - composer require spatie/pdf-to-text (dev dependency)
 * - pdftotext binary installed (brew install poppler on macOS)
 * - OPENAI_API_KEY environment variable set
 */

require_once __DIR__.'/../vendor/autoload.php';

use HelgeSverre\Synapse\Factory;
use Spatie\PdfToText\Pdf;

// Load environment variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
    $dotenv->load();
}

// 1. Extract text from PDF
$pdfPath = __DIR__.'/receipt.pdf';
if (! file_exists($pdfPath)) {
    echo "Error: receipt.pdf not found at {$pdfPath}\n";
    exit(1);
}

try {
    $receiptText = (new Pdf)
        ->setPdf($pdfPath)
        ->text();
} catch (Exception $e) {
    echo "Error extracting PDF text: {$e->getMessage()}\n";
    echo "Make sure pdftotext is installed (brew install poppler on macOS)\n";
    exit(1);
}

if (empty(trim($receiptText))) {
    echo "Error: No text extracted from PDF\n";
    exit(1);
}

echo "📄 Extracted Receipt Text:\n";
echo str_repeat('─', 60)."\n";
echo $receiptText."\n";
echo str_repeat('─', 60)."\n\n";

// 2. Define JSON schema for structured output
$schema = [
    'type' => 'object',
    'properties' => [
        'items' => [
            'type' => 'array',
            'description' => 'List of receipt line items',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'Item description or name',
                    ],
                    'quantity' => [
                        'type' => 'number',
                        'description' => 'Quantity of items',
                    ],
                    'unit_price' => [
                        'type' => 'number',
                        'description' => 'Price per unit',
                    ],
                    'total_price' => [
                        'type' => 'number',
                        'description' => 'Total price for this line item',
                    ],
                ],
                'required' => ['description', 'quantity', 'unit_price', 'total_price'],
                'additionalProperties' => false,
            ],
        ],
        'subtotal' => [
            'type' => 'number',
            'description' => 'Subtotal before tax',
        ],
        'tax' => [
            'type' => 'number',
            'description' => 'Tax amount (set to 0 if not present)',
        ],
        'total' => [
            'type' => 'number',
            'description' => 'Final total amount',
        ],
    ],
    'required' => ['items', 'subtotal', 'tax', 'total'],
    'additionalProperties' => false,
];

// 3. Create prompt for extraction
$prompt = Factory::createTextPrompt()
    ->setContent(<<<'PROMPT'
You are extracting data from a receipt. The receipt text is from a PDF and may have odd formatting.

Look carefully at the actual text. You will see sections with headers like:
- "Description" followed by the item description
- "Qty" followed by the quantity
- "Unit price" followed by the price
- "Amount" followed by the total

In the receipt below, look for:
1. Under "Description", you'll find the actual item name (e.g., "Max plan - 20x")
2. Under "Qty", you'll find the quantity number (e.g., "1")
3. Under "Unit price", you'll find the price (e.g., "$200.00")
4. Under "Amount", you'll find the total (e.g., "$200.00")

Then look for financial totals:
- "Subtotal" line with an amount
- "Total" line with an amount
- If there's no tax mentioned, use 0

DO NOT MAKE UP DATA. Extract only what you see in the text below.

Receipt text:
{{ receipt_text }}
PROMPT
    );

// 4. Create LLM provider
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (! $apiKey) {
    echo "Error: OPENAI_API_KEY environment variable not set\n";
    exit(1);
}

$provider = Factory::useLlm('openai.gpt-4o', [
    'apiKey' => $apiKey,
]);

// 5. Create JSON parser with schema validation
$parser = Factory::createParser('json', [
    'schema' => $schema,
    'validateSchema' => true,
]);

// 6. Create LLM executor with response_format for structured output
$executor = Factory::createLlmExecutor([
    'llm' => $provider,
    'model' => 'gpt-4o',
    'prompt' => $prompt,
    'parser' => $parser,
    'responseFormat' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'receipt_line_items',
            'strict' => true,
            'schema' => $schema,
        ],
    ],
]);

// 7. Execute and parse
echo "🤖 Parsing receipt with OpenAI structured output...\n\n";

try {
    $result = $executor->execute([
        'receipt_text' => $receiptText,
    ]);

    $parsed = $result->value;

    // 8. Display results
    echo "✅ Successfully parsed receipt!\n\n";

    // Show raw JSON for transparency
    echo "📊 Raw JSON:\n".json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";

    echo "📋 Line Items:\n";
    echo str_repeat('─', 80)."\n";
    printf("%-40s %8s %12s %12s\n", 'Description', 'Qty', 'Unit Price', 'Total');
    echo str_repeat('─', 80)."\n";

    foreach ($parsed['items'] as $item) {
        printf(
            "%-40s %8.2f $%11.2f $%11.2f\n",
            $item['description'],
            $item['quantity'],
            $item['unit_price'],
            $item['total_price'],
        );
    }

    echo str_repeat('─', 80)."\n";

    // Display totals if available
    if (isset($parsed['subtotal'])) {
        printf("%63s: $%11.2f\n", 'Subtotal', $parsed['subtotal']);
    }
    if (isset($parsed['tax'])) {
        printf("%63s: $%11.2f\n", 'Tax', $parsed['tax']);
    }
    if (isset($parsed['total'])) {
        printf("%63s: $%11.2f\n", 'TOTAL', $parsed['total']);
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    if ($e->getPrevious()) {
        echo "Cause: {$e->getPrevious()->getMessage()}\n";
    }
    exit(1);
}
