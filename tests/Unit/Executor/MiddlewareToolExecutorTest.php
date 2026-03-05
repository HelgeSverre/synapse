<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\MiddlewareToolExecutor;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Executor\ToolInvocation;
use HelgeSverre\Synapse\Executor\ToolMiddleware;
use HelgeSverre\Synapse\Executor\ToolResult;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use PHPUnit\Framework\TestCase;

final class MiddlewareToolExecutorTest extends TestCase
{
    public function test_middleware_runs_in_order(): void
    {
        $trace = [];

        $inner = new class($trace) implements ToolExecutorInterface
        {
            public array $trace;

            public function __construct(array &$trace)
            {
                $this->trace = &$trace;
            }

            public function getToolDefinitions(): array
            {
                return [new ToolDefinition('noop', 'No-op')];
            }

            public function callFunctionResult(string $name, array $input, ?\HelgeSverre\Synapse\State\ConversationState $state = null): ToolResult
            {
                $this->trace[] = 'inner';

                return ToolResult::success(['ok' => true]);
            }
        };

        $mw1 = new class($trace) implements ToolMiddleware
        {
            public array $trace;

            public function __construct(array &$trace)
            {
                $this->trace = &$trace;
            }

            public function handle(ToolInvocation $invocation, callable $next): ToolResult
            {
                $this->trace[] = 'mw1-before';
                $result = $next($invocation);
                $this->trace[] = 'mw1-after';

                return $result;
            }
        };

        $mw2 = new class($trace) implements ToolMiddleware
        {
            public array $trace;

            public function __construct(array &$trace)
            {
                $this->trace = &$trace;
            }

            public function handle(ToolInvocation $invocation, callable $next): ToolResult
            {
                $this->trace[] = 'mw2-before';
                $result = $next($invocation);
                $this->trace[] = 'mw2-after';

                return $result;
            }
        };

        $executor = (new MiddlewareToolExecutor($inner))
            ->withMiddleware($mw1, $mw2);

        $result = $executor->callFunctionResult('noop', []);

        $this->assertTrue($result->success);
        $this->assertSame(['mw1-before', 'mw2-before', 'inner', 'mw2-after', 'mw1-after'], $trace);
    }

    public function test_middleware_can_short_circuit(): void
    {
        $innerCalled = false;

        $inner = new class($innerCalled) implements ToolExecutorInterface
        {
            public bool $innerCalled;

            public function __construct(bool &$innerCalled)
            {
                $this->innerCalled = &$innerCalled;
            }

            public function getToolDefinitions(): array
            {
                return [new ToolDefinition('noop', 'No-op')];
            }

            public function callFunctionResult(string $name, array $input, ?\HelgeSverre\Synapse\State\ConversationState $state = null): ToolResult
            {
                $this->innerCalled = true;

                return ToolResult::success(['ok' => true]);
            }
        };

        $blocking = new class implements ToolMiddleware
        {
            public function handle(ToolInvocation $invocation, callable $next): ToolResult
            {
                return ToolResult::failure(['blocked']);
            }
        };

        $executor = (new MiddlewareToolExecutor($inner))->withMiddleware($blocking);
        $result = $executor->callFunctionResult('noop', []);

        $this->assertFalse($result->success);
        $this->assertContains('blocked', $result->errors);
        $this->assertFalse($innerCalled);
    }
}
