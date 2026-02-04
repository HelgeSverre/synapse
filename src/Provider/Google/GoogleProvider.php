<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Google;

use Generator;
use HelgeSverre\Synapse\Provider\Http\StreamTransportInterface;
use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\State\Role;
use HelgeSverre\Synapse\Streaming\SseParser;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

/**
 * Google Gemini API provider.
 */
final readonly class GoogleProvider implements StreamableProviderInterface
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
            $messages[] = Message::assistant($text ?? '', $toolCalls);
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

    /**
     * @return Generator<StreamEvent>
     */
    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
    {
        if (! $this->transport instanceof StreamTransportInterface) {
            throw new \RuntimeException(
                'Streaming requires a transport that implements StreamTransportInterface (e.g., GuzzleStreamTransport)',
            );
        }

        $body = $this->buildRequestBody($request);
        $url = "{$this->baseUrl}/models/{$request->model}:streamGenerateContent?key={$this->apiKey}";

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = $this->transport->streamPost($url, $headers, $body, $ctx);

        $toolCallDeltas = [];
        $finishReason = null;
        $usage = null;

        foreach (SseParser::readLines($response->getBody(), $ctx) as $line) {
            if ($ctx?->shouldCancel()) {
                return;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $chunk = json_decode($line, true);
            if (! is_array($chunk)) {
                continue;
            }

            $candidates = $chunk['candidates'] ?? [];
            $candidate = $candidates[0] ?? [];
            $content = $candidate['content'] ?? [];
            $parts = $content['parts'] ?? [];

            foreach ($parts as $index => $part) {
                if (isset($part['text']) && $part['text'] !== '') {
                    yield new TextDelta($part['text']);
                }

                if (isset($part['functionCall'])) {
                    $toolCallId = uniqid('call_');

                    $encodedArgs = isset($part['functionCall']['args'])
                        ? json_encode($part['functionCall']['args'])
                        : null;

                    yield new ToolCallDelta(
                        index: $index,
                        id: $toolCallId,
                        name: $part['functionCall']['name'] ?? null,
                        arguments: $encodedArgs !== false ? $encodedArgs : null,
                    );

                    $toolCallDeltas[] = [
                        'id' => $toolCallId,
                        'name' => $part['functionCall']['name'] ?? '',
                        'arguments' => $part['functionCall']['args'] ?? [],
                    ];
                }
            }

            if (isset($candidate['finishReason'])) {
                $finishReason = $candidate['finishReason'];
            }

            if (isset($chunk['usageMetadata'])) {
                $usage = new UsageInfo(
                    inputTokens: $chunk['usageMetadata']['promptTokenCount'] ?? 0,
                    outputTokens: $chunk['usageMetadata']['candidatesTokenCount'] ?? 0,
                    totalTokens: $chunk['usageMetadata']['totalTokenCount'] ?? null,
                );
            }
        }

        if ($toolCallDeltas !== []) {
            $toolCalls = [];
            foreach ($toolCallDeltas as $tc) {
                $toolCalls[] = new ToolCall(
                    id: $tc['id'],
                    name: $tc['name'],
                    arguments: is_array($tc['arguments']) ? $tc['arguments'] : [],
                );
            }
            yield new ToolCallsReady($toolCalls);
        }

        yield new StreamCompleted(
            finishReason: $finishReason,
            usage: $usage,
        );
    }
}
