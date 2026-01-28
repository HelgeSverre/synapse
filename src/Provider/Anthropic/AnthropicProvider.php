<?php

declare(strict_types=1);

namespace LlmExe\Provider\Anthropic;

use Generator;
use LlmExe\Provider\Http\StreamTransportInterface;
use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\ProviderCapabilities;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolCall;
use LlmExe\Provider\Response\GenerationResponse;
use LlmExe\Provider\Response\UsageInfo;
use LlmExe\State\Message;
use LlmExe\State\Role;
use LlmExe\Streaming\SseParser;
use LlmExe\Streaming\StreamableProviderInterface;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\StreamContext;
use LlmExe\Streaming\StreamEvent;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallDelta;
use LlmExe\Streaming\ToolCallsReady;
use RuntimeException;

final readonly class AnthropicProvider implements LlmProviderInterface, StreamableProviderInterface
{
    private const BASE_URL = 'https://api.anthropic.com/v1';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private TransportInterface $transport,
        private string $apiKey,
        private string $baseUrl = self::BASE_URL,
        private string $apiVersion = self::API_VERSION,
    ) {}

    public function generate(GenerationRequest $request): GenerationResponse
    {
        $body = $this->buildRequestBody($request);
        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];

        $response = $this->transport->post(
            "{$this->baseUrl}/messages",
            $headers,
            $body,
        );

        return $this->parseResponse($response);
    }

    /** @return array<string, mixed> */
    private function buildRequestBody(GenerationRequest $request): array
    {
        $messages = [];

        // Convert messages (Anthropic uses alternating user/assistant)
        foreach ($request->messages as $message) {
            if ($message->role === Role::System) {
                continue; // System prompt handled separately
            }

            $role = match ($message->role) {
                Role::User, Role::Tool, Role::System => 'user',
                Role::Assistant => 'assistant',
            };

            // Handle tool results
            if ($message->role === Role::Tool && $message->toolCallId !== null) {
                $messages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $message->toolCallId,
                            'content' => $message->content,
                        ],
                    ],
                ];
            } elseif ($message->role === Role::Assistant) {
                // Handle assistant messages with potential tool calls
                $toolCalls = $message->getToolCalls();
                if ($toolCalls !== []) {
                    // Anthropic requires content to be an array with text and tool_use blocks
                    $content = [];
                    if ($message->content !== '') {
                        $content[] = ['type' => 'text', 'text' => $message->content];
                    }
                    foreach ($toolCalls as $tc) {
                        $content[] = [
                            'type' => 'tool_use',
                            'id' => $tc->id,
                            'name' => $tc->name,
                            'input' => $tc->arguments,
                        ];
                    }
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $content,
                    ];
                } else {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $message->content,
                    ];
                }
            } else {
                $messages[] = [
                    'role' => $role,
                    'content' => $message->content,
                ];
            }
        }

        $body = [
            'model' => $request->model,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens ?? 4096,
        ];

        // Add system prompt
        $systemPrompt = $request->systemPrompt;
        if ($systemPrompt === null) {
            // Check for system message in messages
            foreach ($request->messages as $message) {
                if ($message->role === Role::System) {
                    $systemPrompt = $message->content;
                    break;
                }
            }
        }
        if ($systemPrompt !== null) {
            $body['system'] = $systemPrompt;
        }

        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }

        if ($request->topP !== null) {
            $body['top_p'] = $request->topP;
        }

        if ($request->stopSequences !== null) {
            $body['stop_sequences'] = $request->stopSequences;
        }

        if (count($request->tools) > 0) {
            $body['tools'] = array_map(
                fn (\LlmExe\Provider\Request\ToolDefinition $tool): array => $tool->toAnthropicFormat(),
                $request->tools,
            );

            if ($request->toolChoice !== null) {
                $body['tool_choice'] = ['type' => $request->toolChoice];
            }
        }

        return $body;
    }

    /** @param array<string, mixed> $response */
    private function parseResponse(array $response): GenerationResponse
    {
        $content = $response['content'] ?? [];
        $text = null;
        $toolCalls = [];
        $messages = [];

        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                $text = ($text ?? '').$block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'] ?? [],
                );
            }
        }

        if ($text !== null || count($toolCalls) > 0) {
            $messages[] = Message::assistant($text ?? '');
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = new UsageInfo(
                inputTokens: $response['usage']['input_tokens'] ?? 0,
                outputTokens: $response['usage']['output_tokens'] ?? 0,
            );
        }

        return new GenerationResponse(
            text: $text,
            messages: $messages,
            toolCalls: $toolCalls,
            model: $response['model'] ?? '',
            usage: $usage,
            finishReason: $response['stop_reason'] ?? null,
            raw: $response,
        );
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTools: true,
            supportsJsonMode: false,
            supportsStreaming: true,
            supportsVision: true,
            supportsSystemPrompt: true,
        );
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    /**
     * @return Generator<StreamEvent>
     */
    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
    {
        if (! $this->transport instanceof StreamTransportInterface) {
            throw new RuntimeException(
                'Streaming requires a transport that implements StreamTransportInterface (e.g., GuzzleStreamTransport)',
            );
        }

        $body = $this->buildRequestBody($request);
        $body['stream'] = true;

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];

        $response = $this->transport->streamPost(
            "{$this->baseUrl}/messages",
            $headers,
            $body,
            $ctx,
        );

        $contentBlocks = [];
        $toolCalls = [];
        $finishReason = null;
        $usage = null;

        foreach (SseParser::parseStream($response->getBody(), $ctx) as $event) {
            if ($ctx?->shouldCancel()) {
                return;
            }

            $eventType = $event['event'];
            $data = $event['data'];

            if ($data === '') {
                continue;
            }

            $chunk = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            switch ($eventType) {
                case 'message_start':
                    if (isset($chunk['message']['usage'])) {
                        $usage = new UsageInfo(
                            inputTokens: $chunk['message']['usage']['input_tokens'] ?? 0,
                            outputTokens: $chunk['message']['usage']['output_tokens'] ?? 0,
                        );
                    }
                    break;

                case 'content_block_start':
                    $index = $chunk['index'] ?? 0;
                    $contentBlock = $chunk['content_block'] ?? [];
                    $blockType = $contentBlock['type'] ?? null;

                    $contentBlocks[$index] = ['type' => $blockType];

                    if ($blockType === 'tool_use') {
                        $toolCalls[$index] = [
                            'id' => $contentBlock['id'] ?? '',
                            'name' => $contentBlock['name'] ?? '',
                            'arguments' => '',
                        ];
                    }
                    break;

                case 'content_block_delta':
                    $index = $chunk['index'] ?? 0;
                    $delta = $chunk['delta'] ?? [];
                    $deltaType = $delta['type'] ?? null;

                    if ($deltaType === 'text_delta') {
                        $text = $delta['text'] ?? '';
                        if ($text !== '') {
                            yield new TextDelta($text);
                        }
                    } elseif ($deltaType === 'input_json_delta') {
                        $partialJson = $delta['partial_json'] ?? '';
                        if (isset($toolCalls[$index])) {
                            $toolCalls[$index]['arguments'] .= $partialJson;

                            yield new ToolCallDelta(
                                index: $index,
                                id: null,
                                name: null,
                                arguments: $partialJson,
                            );
                        }
                    }
                    break;

                case 'content_block_stop':
                    break;

                case 'message_delta':
                    if (isset($chunk['delta']['stop_reason'])) {
                        $finishReason = $chunk['delta']['stop_reason'];
                    }
                    if (isset($chunk['usage'])) {
                        $existingInput = $usage !== null ? $usage->inputTokens : 0;
                        $usage = new UsageInfo(
                            inputTokens: $existingInput,
                            outputTokens: $chunk['usage']['output_tokens'] ?? 0,
                        );
                    }
                    break;

                case 'message_stop':
                    break;
            }
        }

        if ($toolCalls !== []) {
            $readyToolCalls = [];
            foreach ($toolCalls as $tc) {
                $arguments = $tc['arguments'] !== ''
                    ? (json_decode($tc['arguments'], true) ?? [])
                    : [];
                $readyToolCalls[] = new ToolCall(
                    id: $tc['id'],
                    name: $tc['name'],
                    arguments: is_array($arguments) ? $arguments : [],
                );
            }
            yield new ToolCallsReady($readyToolCalls);
        }

        yield new StreamCompleted(
            finishReason: $finishReason,
            usage: $usage,
        );
    }
}
