<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

final readonly class RunResult
{
    public function __construct(
        public bool $success,
        public int $turns,
        public ?string $reportPath,
    ) {}
}
