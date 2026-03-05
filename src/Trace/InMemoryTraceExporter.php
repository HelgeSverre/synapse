<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Trace;

final class InMemoryTraceExporter implements TraceExporterInterface
{
    /** @var list<TraceRecord> */
    private array $records = [];

    public function export(TraceRecord $record): void
    {
        $this->records[] = $record;
    }

    /** @return list<TraceRecord> */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
