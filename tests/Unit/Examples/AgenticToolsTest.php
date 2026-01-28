<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Examples;

use LlmExe\Examples\AgenticTools\CalculatorTool;
use LlmExe\Examples\AgenticTools\DateTimeTool;
use LlmExe\Examples\AgenticTools\NotesTool;
use LlmExe\Examples\AgenticTools\WeatherTool;
use LlmExe\Examples\AgenticTools\WebSearchTool;
use LlmExe\Executor\CallableExecutor;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../examples/agentic-tools/WeatherTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/CalculatorTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/NotesTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/WebSearchTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/DateTimeTool.php';

final class AgenticToolsTest extends TestCase
{
    protected function setUp(): void
    {
        NotesTool::reset();
    }

    public function test_weather_tool_returns_callable_executor(): void
    {
        $tool = WeatherTool::create();

        $this->assertInstanceOf(CallableExecutor::class, $tool);
        $this->assertSame('get_weather', $tool->getName());
    }

    public function test_weather_tool_returns_weather_data(): void
    {
        $tool = WeatherTool::create();
        $result = json_decode($tool->execute(['city' => 'Oslo'])->result, true);

        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('temperature', $result);
        $this->assertArrayHasKey('conditions', $result);
        $this->assertArrayHasKey('humidity', $result);
        $this->assertSame('Oslo', $result['city']);
    }

    public function test_calculator_performs_basic_arithmetic(): void
    {
        $tool = CalculatorTool::create();

        $result1 = json_decode($tool->execute(['expression' => '2 + 2'])->result, true);
        $this->assertSame(4, $result1['result']);

        $result2 = json_decode($tool->execute(['expression' => '10 * 5'])->result, true);
        $this->assertSame(50, $result2['result']);
    }

    public function test_calculator_handles_complex_expressions(): void
    {
        $tool = CalculatorTool::create();

        $result1 = json_decode($tool->execute(['expression' => 'sqrt(16)'])->result, true);
        $this->assertSame(4, $result1['result']);

        $result2 = json_decode($tool->execute(['expression' => '2^3'])->result, true);
        $this->assertSame(8, $result2['result']);
    }

    public function test_notes_add_and_list(): void
    {
        $tool = NotesTool::create();

        $addResult = json_decode($tool->execute(['action' => 'add', 'content' => 'Test note'])->result, true);
        $this->assertTrue($addResult['success']);
        $this->assertSame('Test note', $addResult['note']['content']);

        $listResult = json_decode($tool->execute(['action' => 'list'])->result, true);
        $this->assertTrue($listResult['success']);
        $this->assertSame(1, $listResult['count']);
    }

    public function test_notes_get_and_delete(): void
    {
        $tool = NotesTool::create();

        $addResult = json_decode($tool->execute(['action' => 'add', 'content' => 'Note to delete'])->result, true);
        $noteId = $addResult['note']['id'];

        $getResult = json_decode($tool->execute(['action' => 'get', 'id' => $noteId])->result, true);
        $this->assertTrue($getResult['success']);
        $this->assertSame('Note to delete', $getResult['note']['content']);

        $deleteResult = json_decode($tool->execute(['action' => 'delete', 'id' => $noteId])->result, true);
        $this->assertTrue($deleteResult['success']);

        $listResult = json_decode($tool->execute(['action' => 'list'])->result, true);
        $this->assertSame(0, $listResult['count']);
    }

    public function test_web_search_returns_results(): void
    {
        $tool = WebSearchTool::create();
        $result = json_decode($tool->execute(['query' => 'PHP programming'])->result, true);

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('results_count', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertSame('PHP programming', $result['query']);
        $this->assertIsArray($result['results']);
        $this->assertGreaterThan(0, count($result['results']));
    }

    public function test_datetime_returns_current_time(): void
    {
        $tool = DateTimeTool::create();
        $result = json_decode($tool->execute(['action' => 'now'])->result, true);

        $this->assertArrayHasKey('datetime', $result);
        $this->assertArrayHasKey('timezone', $result);
        $this->assertSame('UTC', $result['timezone']);
    }

    public function test_datetime_calculates_diff(): void
    {
        $tool = DateTimeTool::create();
        $result = json_decode($tool->execute([
            'action' => 'diff',
            'date1' => '2024-01-01',
            'date2' => '2024-01-10',
        ])->result, true);

        $this->assertArrayHasKey('difference', $result);
        $this->assertSame(9, $result['difference']['total_days']);
    }
}
