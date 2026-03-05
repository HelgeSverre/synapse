<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Runtime\Memory;

use HelgeSverre\Synapse\Runtime\Memory\InMemoryMemoryStore;
use PHPUnit\Framework\TestCase;

final class InMemoryMemoryStoreTest extends TestCase
{
    public function test_put_get_and_list_entries(): void
    {
        $store = new InMemoryMemoryStore;

        $entry = $store->put('chat', 'session_1', ['summary' => 'hello'], ['important']);

        $this->assertSame('chat', $entry->namespace);
        $this->assertContains('important', $entry->tags);

        $loaded = $store->get('chat', 'session_1');
        $this->assertNotNull($loaded);
        $this->assertSame('hello', $loaded->value['summary']);

        $list = $store->list('chat');
        $this->assertCount(1, $list);
    }

    public function test_search_by_tag_and_forget(): void
    {
        $store = new InMemoryMemoryStore;

        $store->put('agents', 'a', ['name' => 'alpha'], ['team-a']);
        $store->put('agents', 'b', ['name' => 'beta'], ['team-b']);

        $matches = $store->searchByTag('agents', 'team-a');
        $this->assertCount(1, $matches);
        $this->assertSame('a', $matches[0]->key);

        $store->forget('agents', 'a');
        $this->assertNull($store->get('agents', 'a'));
    }
}
