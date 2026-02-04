<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutor;
use function HelgeSverre\Synapse\createParser;
use function HelgeSverre\Synapse\useLlm;

$systemPrompt = <<<'PROMPT'
We are conducting a test, follow the instructions exactly. Do not ask questions or make conversation. The user will provide an input, you need to reply only with: "Hello World, you said <and then insert here what they said>". So for example, if they say "Hello", you should reply only with: Hello World, you said Hello.
PROMPT;

function helloWorld(string $input): string
{
    global $systemPrompt;

    $llm = useLlm('openai.gpt-4o-mini', [
        'apiKey' => getenv('OPENAI_API_KEY'),
    ]);

    $prompt = createChatPrompt()
        ->addSystemMessage($systemPrompt)
        ->addUserMessage($input);

    $parser = createParser('string');

    return createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => $parser,
        'model' => 'gpt-4o-mini',
    ])->execute([])->getValue();
}

$result = helloWorld('Testing 123');
echo $result."\n";
