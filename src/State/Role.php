<?php

declare(strict_types=1);

namespace LlmExe\State;

enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
