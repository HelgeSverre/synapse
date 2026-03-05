<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Trace;

use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;
use HelgeSverre\Synapse\Hooks\Events\BeforeProviderCall;
use HelgeSverre\Synapse\Hooks\Events\OnComplete;
use HelgeSverre\Synapse\Hooks\Events\OnError;
use HelgeSverre\Synapse\Hooks\Events\OnStreamEnd;
use HelgeSverre\Synapse\Hooks\Events\OnStreamStart;
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;

final class HookTraceBridge
{
    private TraceContext $context;

    private float $runStartedAtMs = 0.0;

    private string $runSpanId = '';

    private bool $runStarted = false;

    private bool $runCompleted = false;

    private bool $registered = false;

    /** @var array<int|string, array{name: string, spanId: string, startedAtMs: float, parentSpanId: string|null, attributes: array<string, mixed>}> */
    private array $openProviderSpans = [];

    /** @var array{name: string, spanId: string, startedAtMs: float, parentSpanId: string|null, attributes: array<string, mixed>}|null */
    private ?array $openStreamSpan = null;

    public function __construct(
        private readonly TraceExporterInterface $exporter,
        ?TraceContext $context = null,
        private readonly string $runName = 'executor.run',
    ) {
        $this->context = $context ?? TraceContext::root();
    }

    public function register(HookDispatcherInterface $hooks): self
    {
        if ($this->registered) {
            return $this;
        }

        $hooks->addListener(BeforeProviderCall::class, function (BeforeProviderCall $event): void {
            $this->ensureRunStarted();

            $request = $event->request;
            $requestKey = (string) spl_object_id($request);

            $this->openProviderSpans[$requestKey] = [
                'name' => 'provider.call',
                'spanId' => $this->generateSpanId(),
                'startedAtMs' => $this->nowMs(),
                'parentSpanId' => $this->runSpanId,
                'attributes' => [
                    'model' => $request->model,
                    'message_count' => count($request->messages),
                    'has_tools' => count($request->tools) > 0,
                ],
            ];
        });

        $hooks->addListener(AfterProviderCall::class, function (AfterProviderCall $event): void {
            $requestKey = (string) spl_object_id($event->request);
            $span = $this->openProviderSpans[$requestKey] ?? null;
            unset($this->openProviderSpans[$requestKey]);

            if ($span === null) {
                return;
            }

            $usage = $event->response->usage;
            $attributes = $span['attributes'];
            if ($usage !== null) {
                $attributes = [
                    ...$attributes,
                    'input_tokens' => $usage->inputTokens,
                    'output_tokens' => $usage->outputTokens,
                    'total_tokens' => $usage->getTotal(),
                ];
            }

            $this->exportRecord(
                name: $span['name'],
                spanId: $span['spanId'],
                parentSpanId: $span['parentSpanId'],
                startedAtMs: $span['startedAtMs'],
                endedAtMs: $this->nowMs(),
                success: true,
                attributes: $attributes,
            );
        });

        $hooks->addListener(OnStreamStart::class, function (OnStreamStart $event): void {
            $this->ensureRunStarted();

            $this->openStreamSpan = [
                'name' => 'stream.call',
                'spanId' => $this->generateSpanId(),
                'startedAtMs' => $this->nowMs(),
                'parentSpanId' => $this->runSpanId,
                'attributes' => [
                    'model' => $event->request->model,
                    'message_count' => count($event->request->messages),
                    'has_tools' => count($event->request->tools) > 0,
                ],
            ];
        });

        $hooks->addListener(OnStreamEnd::class, function (OnStreamEnd $event): void {
            $span = $this->openStreamSpan;
            $this->openStreamSpan = null;

            if ($span === null) {
                return;
            }

            $attributes = [
                ...$span['attributes'],
                'finish_reason' => $event->completed->finishReason,
            ];

            if ($event->completed->usage !== null) {
                $attributes = [
                    ...$attributes,
                    'input_tokens' => $event->completed->usage->inputTokens,
                    'output_tokens' => $event->completed->usage->outputTokens,
                    'total_tokens' => $event->completed->usage->getTotal(),
                ];
            }

            $this->exportRecord(
                name: $span['name'],
                spanId: $span['spanId'],
                parentSpanId: $span['parentSpanId'],
                startedAtMs: $span['startedAtMs'],
                endedAtMs: $this->nowMs(),
                success: true,
                attributes: $attributes,
            );
        });

        $hooks->addListener(OnToolCall::class, function (OnToolCall $event): void {
            $this->ensureRunStarted();

            $timestamp = $this->nowMs();

            $this->exportRecord(
                name: 'tool.call',
                spanId: $this->generateSpanId(),
                parentSpanId: $this->runSpanId,
                startedAtMs: $timestamp,
                endedAtMs: $timestamp,
                success: true,
                attributes: [
                    'tool_name' => $event->toolCall->name,
                    'tool_call_id' => $event->toolCall->id,
                ],
            );
        });

        $hooks->addListener(OnError::class, function (OnError $event): void {
            $this->ensureRunStarted();

            $timestamp = $this->nowMs();

            foreach ($this->openProviderSpans as $requestKey => $span) {
                $this->exportRecord(
                    name: $span['name'],
                    spanId: $span['spanId'],
                    parentSpanId: $span['parentSpanId'],
                    startedAtMs: $span['startedAtMs'],
                    endedAtMs: $timestamp,
                    success: false,
                    attributes: $span['attributes'],
                    error: $event->error->getMessage(),
                );

                unset($this->openProviderSpans[$requestKey]);
            }

            if ($this->openStreamSpan !== null) {
                $span = $this->openStreamSpan;
                $this->openStreamSpan = null;

                $this->exportRecord(
                    name: $span['name'],
                    spanId: $span['spanId'],
                    parentSpanId: $span['parentSpanId'],
                    startedAtMs: $span['startedAtMs'],
                    endedAtMs: $timestamp,
                    success: false,
                    attributes: $span['attributes'],
                    error: $event->error->getMessage(),
                );
            }

            $this->exportRecord(
                name: 'executor.error',
                spanId: $this->generateSpanId(),
                parentSpanId: $this->runSpanId,
                startedAtMs: $timestamp,
                endedAtMs: $timestamp,
                success: false,
                attributes: [
                    'error_type' => $event->error::class,
                ],
                error: $event->error->getMessage(),
            );
        });

        $hooks->addListener(OnComplete::class, function (OnComplete $event): void {
            if ($this->runCompleted && $this->runStarted) {
                return;
            }

            if (! $this->runStarted || $this->runCompleted) {
                $endedAtMs = $this->nowMs();
                $startedAtMs = max(0.0, $endedAtMs - $event->durationMs);
                $this->startRun($startedAtMs);
            }

            $this->runCompleted = true;

            $this->exportRecord(
                name: $this->runName,
                spanId: $this->runSpanId,
                parentSpanId: $this->context->parentSpanId,
                startedAtMs: $this->runStartedAtMs,
                endedAtMs: $this->runStartedAtMs + $event->durationMs,
                success: $event->success,
                attributes: [
                    'duration_ms' => $event->durationMs,
                ],
                error: $event->error?->getMessage(),
            );
        });

        $this->registered = true;

        return $this;
    }

    public function getContext(): TraceContext
    {
        return $this->context;
    }

    private function ensureRunStarted(): void
    {
        if (! $this->runStarted || $this->runCompleted) {
            $this->startRun();
        }
    }

    private function startRun(?float $startedAtMs = null): void
    {
        $this->runSpanId = $this->generateSpanId();
        $this->runStartedAtMs = $startedAtMs ?? $this->nowMs();
        $this->runStarted = true;
        $this->runCompleted = false;
    }

    private function exportRecord(
        string $name,
        string $spanId,
        ?string $parentSpanId,
        float $startedAtMs,
        float $endedAtMs,
        bool $success,
        array $attributes = [],
        ?string $error = null,
    ): void {
        $this->exporter->export(new TraceRecord(
            traceId: $this->context->traceId,
            runId: $this->context->runId,
            spanId: $spanId,
            parentSpanId: $parentSpanId,
            name: $name,
            startedAtMs: $startedAtMs,
            endedAtMs: $endedAtMs,
            success: $success,
            attributes: [...$this->context->attributes, ...$attributes],
            error: $error,
        ));
    }

    private function nowMs(): float
    {
        return microtime(true) * 1000;
    }

    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
