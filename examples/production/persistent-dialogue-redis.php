<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\State\Message;

use function HelgeSverre\Synapse\useLlm;

/**
 * Minimal history persistence pattern.
 * Replace this with a proper repository in production.
 */
final class SessionHistoryStore
{
    public function __construct(private Redis $redis) {}

    /** @return list<Message> */
    public function load(string $sessionId): array
    {
        $raw = $this->redis->lRange("chat:{$sessionId}", 0, -1);

        return array_values(array_filter(array_map(
            function (string $row): ?Message {
                $decoded = json_decode($row, true);
                if (! is_array($decoded)) {
                    return null;
                }

                return new Message(
                    role: \HelgeSverre\Synapse\State\Role::from($decoded['role']),
                    content: (string) $decoded['content'],
                    name: $decoded['name'] ?? null,
                    toolCallId: $decoded['toolCallId'] ?? null,
                );
            },
            $raw,
        )));
    }

    /** @param list<Message> $messages */
    public function save(string $sessionId, array $messages, int $maxMessages = 40): void
    {
        $this->redis->del("chat:{$sessionId}");

        foreach (array_slice($messages, -$maxMessages) as $message) {
            $this->redis->rPush("chat:{$sessionId}", json_encode($message->toArray(), JSON_THROW_ON_ERROR));
        }

        $this->redis->expire("chat:{$sessionId}", 86400);
    }
}

if (! class_exists(Redis::class)) {
    throw new RuntimeException('Install ext-redis to run this example.');
}

$redis = new Redis;
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int) (getenv('REDIS_PORT') ?: 6379));
$store = new SessionHistoryStore($redis);

$sessionId = 'demo-user-123';
$history = $store->load($sessionId);

$llm = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);
$executor = createExecutor(new ExecutorOptions(
    llm: $llm,
    prompt: createChatPrompt()
        ->addSystemMessage('You are a customer support assistant.')
        ->addUserMessage('{{message}}', parseTemplate: true),
    parser: createParser('string'),
));

$userMessage = 'Remind me what we talked about earlier.';
$result = $executor->run(['message' => $userMessage], $history);

$newHistory = [
    ...$history,
    Message::user($userMessage),
    Message::assistant((string) $result->getValue()),
];

$store->save($sessionId, $newHistory);
echo $result->getValue()."\n";
