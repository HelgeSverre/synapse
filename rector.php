<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withSkip([
        __DIR__.'/vendor',
        __DIR__.'/tests',  // Don't refactor tests
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
    ])
    // Minimal, safe transformations only
    ->withPreparedSets(
        deadCode: false,       // Can remove intentional code
        codeQuality: false,    // Often makes code worse
        typeDeclarations: true, // Helpful for adding types
        earlyReturn: false,    // Style preference, can reduce readability
    );
