<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Trace;

interface TraceExporterInterface
{
    public function export(TraceRecord $record): void;
}
