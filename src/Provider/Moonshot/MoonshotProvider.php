<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Moonshot;

use Generator;
use HelgeSverre\Synapse\Provider\Http\StreamTransportInterface;
use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\SseParser;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

final readonly class MoonshotProvider implements LlmProviderInterface, StreamableProviderInterface
{
    private const BASE_URL = 'https://api.moonshot.ai/v1';

    public function __construct(
        private TransportInterface $transport,
        private string $apiKey,
        private string $baseUrl = self::BASE_URL,
    ) {}

    public function generate(GenerationRequest $request): GenerationResponse
    {
        $body = $this->buildRequestBody($request);
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        $response = $this->transport->post(
            "{$this->baseUrl}/chat/completions",
            $headers,
            $body,
        );

        return $this->parseResponse($response);
    }

    /** @return array<string, mixed> */
    private function buildRequestBody(GenerationRequest $request): array
    {
        $messages = [];

        if ($request->systemPrompt !== null) {
            $messages[] = [
                'role' => 'system',
                'content' => $request->systemPrompt,
            ];
        }

        foreach ($request->messages as $message) {
            $msg = [
                'role' => $message->role->value,
                'content' => $message->content,
            ];

            if ($message->name !== null) {
                $msg['name'] = $message->name;
            }

            if ($message->toolCallId !== null) {
                $msg['tool_call_id'] = $message->toolCallId;
            }

            $toolCalls = $message->getToolCalls();
            if ($toolCalls !== []) {
                $msg['tool_calls'] = array_map(
                    fn (\HelgeSverre\Synapse\Provider\Request\ToolCall $tc): array => [
                        'id' => $tc->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $tc->name,
                            'arguments' => json_encode($tc->arguments),
                        ],
                    ],
                    $toolCalls,
                );
            }

            $messages[] = $msg;
        }

        $body = [
            'model' => $request->model,
            'messages' => $messages,
        ];

        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $body['max_tokens'] = $request->maxTokens;
        }

        if ($request->topP !== null) {
            $body['top_p'] = $request->topP;
        }

        if ($request->stopSequences !== null) {
            $body['stop'] = $request->stopSequences;
        }

        if (count($request->tools) > 0) {
            $body['tools'] = array_map(
                fn (\HelgeSverre\Synapse\Provider\Request\ToolDefinition $tool): array => $tool->toOpenAIFormat(),
                $request->tools,
            );

            if ($request->toolChoice !== null) {
                $body['tool_choice'] = $request->toolChoice;
            }
        }

        if ($request->responseFormat !== null) {
            $body['response_format'] = $request->responseFormat;
        }

        return $body;
    }

    /** @param array<string, mixed> $response */
    private function parseResponse(array $response): GenerationResponse
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $text = $message['content'] ?? null;
        $toolCalls = [];
        $messages = [];

        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                if ($toolCall['type'] === 'function') {
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];
                    $toolCalls[] = new ToolCall(
                        id: $toolCall['id'],
                        name: $toolCall['function']['name'],
                        arguments: $arguments,
                    );
                }
            }
        }

        if ($text !== null || count($toolCalls) > 0) {
            $messages[] = Message::assistant($text ?? '', $toolCalls);
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = new UsageInfo(
                inputTokens: $response['usage']['prompt_tokens'] ?? 0,
                outputTokens: $response['usage']['completion_tokens'] ?? 0,
                totalTokens: $response['usage']['total_tokens'] ?? null,
            );
        }

        return new GenerationResponse(
            text: $text,
            messages: $messages,
            toolCalls: $toolCalls,
            model: $response['model'] ?? '',
            usage: $usage,
            finishReason: $choice['finish_reason'] ?? null,
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
        return 'moonshot';
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
        $body['stream'] = true;
        // Note: Moonshot claims OpenAI compatibility but stream_options support may vary
        // Usage is included in the last chunk's usage field regardless

        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        $response = $this->transport->streamPost(
            "{$this->baseUrl}/chat/completions",
            $headers,
            $body,
            $ctx,
        );

        $toolCallDeltas = [];
        $finishReason = null;
        $usage = null;

        foreach (SseParser::parseStream($response->getBody(), $ctx) as $event) {
            if ($ctx?->shouldCancel()) {
                return;
            }

            $data = $event['data'];

            if ($data === '[DONE]') {
                break;
            }

            if ($data === '') {
                continue;
            }

            $chunk = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            $choice = $chunk['choices'][0] ?? null;

            if ($choice === null) {
                if (isset($chunk['usage'])) {
                    $usage = new UsageInfo(
                        inputTokens: $chunk['usage']['prompt_tokens'] ?? 0,
                        outputTokens: $chunk['usage']['completion_tokens'] ?? 0,
                        totalTokens: $chunk['usage']['total_tokens'] ?? null,
                    );
                }

                continue;
            }

            $delta = $choice['delta'] ?? [];

            if (isset($delta['content']) && $delta['content'] !== '') {
                yield new TextDelta($delta['content']);
            }

            if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCallChunk) {
                    $index = $toolCallChunk['index'] ?? 0;

                    yield new ToolCallDelta(
                        index: $index,
                        id: $toolCallChunk['id'] ?? null,
                        name: $toolCallChunk['function']['name'] ?? null,
                        arguments: $toolCallChunk['function']['arguments'] ?? null,
                    );

                    if (! isset($toolCallDeltas[$index])) {
                        $toolCallDeltas[$index] = ['id' => '', 'name' => '', 'arguments' => ''];
                    }
                    if (isset($toolCallChunk['id'])) {
                        $toolCallDeltas[$index]['id'] = $toolCallChunk['id'];
                    }
                    if (isset($toolCallChunk['function']['name'])) {
                        $toolCallDeltas[$index]['name'] = $toolCallChunk['function']['name'];
                    }
                    if (isset($toolCallChunk['function']['arguments'])) {
                        $toolCallDeltas[$index]['arguments'] .= $toolCallChunk['function']['arguments'];
                    }
                }
            }

            if (isset($choice['finish_reason']) && is_string($choice['finish_reason'])) {
                $finishReason = $choice['finish_reason'];
            }

            if (isset($chunk['usage'])) {
                $usage = new UsageInfo(
                    inputTokens: $chunk['usage']['prompt_tokens'] ?? 0,
                    outputTokens: $chunk['usage']['completion_tokens'] ?? 0,
                    totalTokens: $chunk['usage']['total_tokens'] ?? null,
                );
            }
        }

        if ($toolCallDeltas !== []) {
            $toolCalls = [];
            foreach ($toolCallDeltas as $tc) {
                $arguments = $tc['arguments'] !== ''
                    ? (json_decode($tc['arguments'], true) ?? [])
                    : [];
                $toolCalls[] = new ToolCall(
                    id: $tc['id'],
                    name: $tc['name'],
                    arguments: is_array($arguments) ? $arguments : [],
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
