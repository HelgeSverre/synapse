<?php

declare(strict_types=1);

namespace LlmExe\Provider\Mistral;

use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\ProviderCapabilities;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolCall;
use LlmExe\Provider\Response\GenerationResponse;
use LlmExe\Provider\Response\UsageInfo;
use LlmExe\State\Message;

final readonly class MistralProvider implements LlmProviderInterface
{
    private const BASE_URL = 'https://api.mistral.ai/v1';

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
                fn (\LlmExe\Provider\Request\ToolDefinition $tool): array => $tool->toOpenAIFormat(),
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
            $messages[] = Message::assistant($text ?? '');
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
        return 'mistral';
    }
}
