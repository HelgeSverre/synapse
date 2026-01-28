<?php

declare(strict_types=1);

namespace LlmExe\Provider\Bedrock;

use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\ProviderCapabilities;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolCall;
use LlmExe\Provider\Response\GenerationResponse;
use LlmExe\Provider\Response\UsageInfo;
use LlmExe\State\Message;
use LlmExe\State\Role;

/**
 * AWS Bedrock provider.
 * Supports Claude models via the Bedrock Converse API.
 *
 * Note: This provider requires AWS credentials to be configured.
 * You must use a custom transport that handles AWS Signature V4 signing.
 */
final readonly class BedrockProvider implements LlmProviderInterface
{
    public function __construct(
        private TransportInterface $transport,
        private string $region,
        private ?string $baseUrl = null,
    ) {}

    public function generate(GenerationRequest $request): GenerationResponse
    {
        $body = $this->buildRequestBody($request);

        $url = $this->baseUrl ?? "https://bedrock-runtime.{$this->region}.amazonaws.com";
        $endpoint = "{$url}/model/{$request->model}/converse";

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $response = $this->transport->post($endpoint, $headers, $body);

        return $this->parseResponse($response, $request->model);
    }

    /** @return array<string, mixed> */
    private function buildRequestBody(GenerationRequest $request): array
    {
        $messages = [];

        // Convert messages to Bedrock Converse format
        foreach ($request->messages as $message) {
            if ($message->role === Role::System) {
                continue; // System handled separately
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
                            'toolResult' => [
                                'toolUseId' => $message->toolCallId,
                                'content' => [
                                    ['text' => $message->content],
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                $messages[] = [
                    'role' => $role,
                    'content' => [
                        ['text' => $message->content],
                    ],
                ];
            }
        }

        $body = ['messages' => $messages];

        // System prompt
        $systemPrompt = $request->systemPrompt;
        if ($systemPrompt === null) {
            foreach ($request->messages as $message) {
                if ($message->role === Role::System) {
                    $systemPrompt = $message->content;
                    break;
                }
            }
        }
        if ($systemPrompt !== null) {
            $body['system'] = [['text' => $systemPrompt]];
        }

        // Inference config
        $inferenceConfig = [];
        if ($request->temperature !== null) {
            $inferenceConfig['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $inferenceConfig['maxTokens'] = $request->maxTokens;
        }
        if ($request->topP !== null) {
            $inferenceConfig['topP'] = $request->topP;
        }
        if ($request->stopSequences !== null) {
            $inferenceConfig['stopSequences'] = $request->stopSequences;
        }
        if ($inferenceConfig !== []) {
            $body['inferenceConfig'] = $inferenceConfig;
        }

        // Tools
        if (count($request->tools) > 0) {
            $toolConfig = ['tools' => []];
            foreach ($request->tools as $tool) {
                $toolConfig['tools'][] = [
                    'toolSpec' => [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'inputSchema' => [
                            'json' => $tool->parameters ?: ['type' => 'object', 'properties' => new \stdClass],
                        ],
                    ],
                ];
            }
            $body['toolConfig'] = $toolConfig;
        }

        return $body;
    }

    /** @param array<string, mixed> $response */
    private function parseResponse(array $response, string $model): GenerationResponse
    {
        $output = $response['output'] ?? [];
        $message = $output['message'] ?? [];
        $content = $message['content'] ?? [];

        $text = null;
        $toolCalls = [];
        $messages = [];

        foreach ($content as $block) {
            if (isset($block['text'])) {
                $text = ($text ?? '').$block['text'];
            } elseif (isset($block['toolUse'])) {
                $toolCalls[] = new ToolCall(
                    id: $block['toolUse']['toolUseId'],
                    name: $block['toolUse']['name'],
                    arguments: $block['toolUse']['input'] ?? [],
                );
            }
        }

        if ($text !== null || count($toolCalls) > 0) {
            $messages[] = Message::assistant($text ?? '');
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = new UsageInfo(
                inputTokens: $response['usage']['inputTokens'] ?? 0,
                outputTokens: $response['usage']['outputTokens'] ?? 0,
                totalTokens: $response['usage']['totalTokens'] ?? null,
            );
        }

        return new GenerationResponse(
            text: $text,
            messages: $messages,
            toolCalls: $toolCalls,
            model: $model,
            usage: $usage,
            finishReason: $response['stopReason'] ?? null,
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
        return 'bedrock';
    }
}
