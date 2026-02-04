<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Prompt;

enum PromptType: string
{
    case Text = 'text';
    case Chat = 'chat';
}
