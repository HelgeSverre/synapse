<?php

declare(strict_types=1);

namespace LlmExe\Provider\Google;

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
 * Google Gemini API provider.
 */
final readonly class GoogleProvider implements LlmProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        private TransportInterface $transport,
        private string $apiKey,
        private string $baseUrl = self::BASE_URL,
    ) {}

    public function generate(GenerationRequest $request): GenerationResponse
    {
        $body = $this->buildRequestBody($request);
        $url = "{$this->baseUrl}/models/{$request->model}:generateContent?key={$this->apiKey}";

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = $this->transport->post($url, $headers, $body);

        return $this->parseResponse($response, $request->model);
    }

    /** @return array<string, mixed> */
    private function buildRequestBody(GenerationRequest $request): array
    {
        $contents = [];

        // Add system instruction if provided
        $systemInstruction = null;
        if ($request->systemPrompt !== null) {
            $systemInstruction = ['parts' => [['text' => $request->systemPrompt]]];
        }

        // Convert messages to Gemini format
        foreach ($request->messages as $message) {
            if ($message->role === Role::System) {
                $systemInstruction = ['parts' => [['text' => $message->content]]];

                continue;
            }

            $role = match ($message->role) {
                Role::User, Role::Tool, Role::System => 'user',
                Role::Assistant => 'model',
            };

            // Handle tool responses
            if ($message->role === Role::Tool && $message->toolCallId !== null) {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $message->name ?? 'function',
                                'response' => [
                                    'result' => $message->content,
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $message->content]],
                ];
            }
        }

        $body = ['contents' => $contents];

        if ($systemInstruction !== null) {
            $body['systemInstruction'] = $systemInstruction;
        }

        // Generation config
        $generationConfig = [];
        if ($request->temperature !== null) {
            $generationConfig['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $request->maxTokens;
        }
        if ($request->topP !== null) {
            $generationConfig['topP'] = $request->topP;
        }
        if ($request->stopSequences !== null) {
            $generationConfig['stopSequences'] = $request->stopSequences;
        }
        if ($generationConfig !== []) {
            $body['generationConfig'] = $generationConfig;
        }

        // Tools
        if (count($request->tools) > 0) {
            $functionDeclarations = [];
            foreach ($request->tools as $tool) {
                $functionDeclarations[] = [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters ?: ['type' => 'object', 'properties' => new \stdClass],
                ];
            }
            $body['tools'] = [['functionDeclarations' => $functionDeclarations]];
        }

        return $body;
    }

    /** @param array<string, mixed> $response */
    private function parseResponse(array $response, string $model): GenerationResponse
    {
        $candidates = $response['candidates'] ?? [];
        $candidate = $candidates[0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $text = null;
        $toolCalls = [];
        $messages = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text = ($text ?? '').$part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    id: uniqid('call_'),
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                );
            }
        }

        if ($text !== null || count($toolCalls) > 0) {
            $messages[] = Message::assistant($text ?? '');
        }

        $usage = null;
        if (isset($response['usageMetadata'])) {
            $usage = new UsageInfo(
                inputTokens: $response['usageMetadata']['promptTokenCount'] ?? 0,
                outputTokens: $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                totalTokens: $response['usageMetadata']['totalTokenCount'] ?? null,
            );
        }

        return new GenerationResponse(
            text: $text,
            messages: $messages,
            toolCalls: $toolCalls,
            model: $model,
            usage: $usage,
            finishReason: $candidate['finishReason'] ?? null,
            raw: $response,
        );
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTools: true,
            supportsJsonMode: true,
            supportsStreaming: true,
            supportsVision: true,
            supportsSystemPrompt: true,
        );
    }

    public function getName(): string
    {
        return 'google';
    }
}
