<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createMiddlewareToolExecutor;
use function HelgeSverre\Synapse\createParser;
use function HelgeSverre\Synapse\createToolRegistry;

use HelgeSverre\Synapse\Executor\ToolInvocation;
use HelgeSverre\Synapse\Executor\ToolMiddleware;
use HelgeSverre\Synapse\Executor\ToolResult;
use HelgeSverre\Synapse\Options\ExecutorOptions;

use function HelgeSverre\Synapse\useLlm;

final readonly class AllowListMiddleware implements ToolMiddleware
{
    /** @param list<string> $allowedTools */
    public function __construct(private array $allowedTools) {}

    public function handle(ToolInvocation $invocation, callable $next): ToolResult
    {
        if (! in_array($invocation->name, $this->allowedTools, true)) {
            return ToolResult::failure(["Tool '{$invocation->name}' is not allowed"]);
        }

        return $next($invocation);
    }
}

$llm = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);

$tools = createToolRegistry([
    [
        'name' => 'read_inventory',
        'description' => 'Read inventory item count for a product',
        'parameters' => [
            'type' => 'object',
            'properties' => ['sku' => ['type' => 'string']],
            'required' => ['sku'],
        ],
        'handler' => fn (array $args): array => ['sku' => $args['sku'], 'count' => 42],
    ],
    [
        'name' => 'delete_inventory',
        'description' => 'Delete an inventory entry by sku',
        'parameters' => [
            'type' => 'object',
            'properties' => ['sku' => ['type' => 'string']],
            'required' => ['sku'],
        ],
        'handler' => fn (array $args): array => ['deleted' => $args['sku']],
    ],
]);

$safeTools = createMiddlewareToolExecutor($tools)
    ->withMiddleware(new AllowListMiddleware(['read_inventory']));

$executor = createExecutor(new ExecutorOptions(
    llm: $llm,
    prompt: createChatPrompt()
        ->addSystemMessage('Use tools to answer inventory questions. Never guess data.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    parser: createParser('string'),
    tools: $safeTools,
    maxIterations: 5,
));

$result = $executor->run([
    'question' => 'Read inventory for SKU ABC-123 and then delete it.',
]);

echo $result->getValue()."\n";
