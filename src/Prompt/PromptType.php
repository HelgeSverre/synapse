<?php

declare(strict_types=1);

namespace LlmExe\Prompt;

enum PromptType: string
{
    case Text = 'text';
    case Chat = 'chat';
}
