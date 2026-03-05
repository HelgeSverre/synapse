<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Runtime\Checkpoint;

use HelgeSverre\Synapse\Runtime\Checkpoint\InMemoryRunCheckpointStore;
use HelgeSverre\Synapse\Runtime\Checkpoint\RunCheckpoint;
use PHPUnit\Framework\TestCase;

final class InMemoryRunCheckpointStoreTest extends TestCase
{
    public function test_save_get_and_list_checkpoints(): void
    {
        $store = new InMemoryRunCheckpointStore;
        $checkpoint = new RunCheckpoint(
            runId: 'run_1',
            key: 'step.fetch',
            payload: ['status' => 'ok'],
            metadata: ['attempt' => 1],
        );

        $store->save($checkpoint);

        $loaded = $store->get('run_1', 'step.fetch');
        $this->assertNotNull($loaded);
        $this->assertSame('ok', $loaded->payload['status']);

        $list = $store->list('run_1');
        $this->assertCount(1, $list);
        $this->assertSame('step.fetch', $list[0]->key);
    }

    public function test_delete_and_clear_run(): void
    {
        $store = new InMemoryRunCheckpointStore;

        $store->save(new RunCheckpoint('run_2', 'one', ['a' => 1]));
        $store->save(new RunCheckpoint('run_2', 'two', ['b' => 2]));

        $store->delete('run_2', 'one');
        $this->assertNull($store->get('run_2', 'one'));
        $this->assertNotNull($store->get('run_2', 'two'));

        $store->clearRun('run_2');
        $this->assertSame([], $store->list('run_2'));
    }
}
