<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

enum ParserTarget: string
{
    case Text = 'text';
    case FunctionCall = 'function_call';
}
