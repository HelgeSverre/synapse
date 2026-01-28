<?php

declare(strict_types=1);

namespace LlmExe\Provider\Anthropic;

use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\ProviderCapabilities;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolCall;
use LlmExe\Provider\Response\GenerationResponse;
use LlmExe\Provider\Response\UsageInfo;
use LlmExe\State\Message;
use LlmExe\State\Role;

final readonly class AnthropicProvider implements LlmProviderInterface
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
}
