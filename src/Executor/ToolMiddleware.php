<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

interface ToolMiddleware
{
    /**
     * @param  callable(ToolInvocation): ToolResult  $next
     */
    public function handle(ToolInvocation $invocation, callable $next): ToolResult;
}
