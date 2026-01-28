<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit;

use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\CoreExecutor;
use LlmExe\Executor\LlmExecutor;
use LlmExe\Executor\LlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Factory;
use LlmExe\Parser\BooleanParser;
use LlmExe\Parser\CustomParser;
use LlmExe\Parser\EnumParser;
use LlmExe\Parser\JsonParser;
use LlmExe\Parser\ListParser;
use LlmExe\Parser\ListToJsonParser;
use LlmExe\Parser\ListToKeyValueParser;
use LlmExe\Parser\LlmFunctionParser;
use LlmExe\Parser\MarkdownCodeBlockParser;
use LlmExe\Parser\MarkdownCodeBlocksParser;
use LlmExe\Parser\NumberParser;
use LlmExe\Parser\ReplaceStringTemplateParser;
use LlmExe\Parser\StringParser;
use LlmExe\Prompt\ChatPrompt;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Bedrock\BedrockProvider;
use LlmExe\Provider\Google\GoogleProvider;
use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\Provider\XAI\XAIProvider;
use LlmExe\State\ConversationState;
use LlmExe\State\Dialogue;
use PHPUnit\Framework\TestCase;

final class FactoryTest extends TestCase
{
    private TransportInterface $mockTransport;

    protected function setUp(): void
    {
        $this->mockTransport = $this->createMock(TransportInterface::class);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Factory::class);
        $property = $reflection->getProperty('defaultTransport');
        $property->setValue(null, null);
    }

    public function test_set_and_get_default_transport(): void
    {
        Factory::setDefaultTransport($this->mockTransport);

        $this->assertSame($this->mockTransport, Factory::getDefaultTransport());
    }

    public function test_get_default_transport_discovers_guzzle(): void
    {
        $transport = Factory::getDefaultTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function test_use_llm_creates_openai_provider(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_use_llm_creates_anthropic_provider(): void
    {
        $provider = Factory::useLlm('anthropic.claude-3', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(AnthropicProvider::class, $provider);
    }

    public function test_use_llm_creates_google_provider(): void
    {
        $provider = Factory::useLlm('google.gemini-pro', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(GoogleProvider::class, $provider);
    }

    public function test_use_llm_creates_google_provider_with_gemini_alias(): void
    {
        $provider = Factory::useLlm('gemini.gemini-pro', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(GoogleProvider::class, $provider);
    }

    public function test_use_llm_creates_xai_provider(): void
    {
        $provider = Factory::useLlm('xai.grok-1', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(XAIProvider::class, $provider);
    }

    public function test_use_llm_creates_xai_provider_with_grok_alias(): void
    {
        $provider = Factory::useLlm('grok.grok-1', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(XAIProvider::class, $provider);
    }

    public function test_use_llm_creates_bedrock_provider(): void
    {
        $provider = Factory::useLlm('bedrock.claude-3', [
            'region' => 'us-east-1',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(BedrockProvider::class, $provider);
    }

    public function test_use_llm_throws_for_missing_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('apiKey is required');

        Factory::useLlm('openai.gpt-4', [
            'transport' => $this->mockTransport,
        ]);
    }

    public function test_use_llm_throws_for_missing_region_on_bedrock(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('region is required');

        Factory::useLlm('bedrock.claude-3', [
            'transport' => $this->mockTransport,
        ]);
    }

    public function test_use_llm_throws_for_unknown_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider: unknown');

        Factory::useLlm('unknown.model', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);
    }

    public function test_use_llm_accepts_custom_base_url(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'baseUrl' => 'https://custom.api.com/v1',
            'transport' => $this->mockTransport,
        ]);

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_create_text_prompt(): void
    {
        $prompt = Factory::createTextPrompt();

        $this->assertInstanceOf(TextPrompt::class, $prompt);
    }

    public function test_create_chat_prompt(): void
    {
        $prompt = Factory::createChatPrompt();

        $this->assertInstanceOf(ChatPrompt::class, $prompt);
    }

    public function test_create_parser_string(): void
    {
        $parser = Factory::createParser('string');

        $this->assertInstanceOf(StringParser::class, $parser);
    }

    public function test_create_parser_string_with_trim_option(): void
    {
        $parser = Factory::createParser('string', ['trim' => false]);

        $this->assertInstanceOf(StringParser::class, $parser);
    }

    public function test_create_parser_boolean(): void
    {
        $parser = Factory::createParser('boolean');

        $this->assertInstanceOf(BooleanParser::class, $parser);
    }

    public function test_create_parser_bool_alias(): void
    {
        $parser = Factory::createParser('bool');

        $this->assertInstanceOf(BooleanParser::class, $parser);
    }

    public function test_create_parser_number(): void
    {
        $parser = Factory::createParser('number');

        $this->assertInstanceOf(NumberParser::class, $parser);
    }

    public function test_create_parser_int_alias(): void
    {
        $parser = Factory::createParser('int');

        $this->assertInstanceOf(NumberParser::class, $parser);
    }

    public function test_create_parser_float_alias(): void
    {
        $parser = Factory::createParser('float');

        $this->assertInstanceOf(NumberParser::class, $parser);
    }

    public function test_create_parser_number_with_int_only_option(): void
    {
        $parser = Factory::createParser('number', ['intOnly' => true]);

        $this->assertInstanceOf(NumberParser::class, $parser);
    }

    public function test_create_parser_json(): void
    {
        $parser = Factory::createParser('json');

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    public function test_create_parser_json_with_schema(): void
    {
        $schema = ['type' => 'object'];
        $parser = Factory::createParser('json', ['schema' => $schema]);

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    public function test_create_parser_list(): void
    {
        $parser = Factory::createParser('list');

        $this->assertInstanceOf(ListParser::class, $parser);
    }

    public function test_create_parser_array_alias(): void
    {
        $parser = Factory::createParser('array');

        $this->assertInstanceOf(ListParser::class, $parser);
    }

    public function test_create_parser_code(): void
    {
        $parser = Factory::createParser('code');

        $this->assertInstanceOf(MarkdownCodeBlockParser::class, $parser);
    }

    public function test_create_parser_codeblock_alias(): void
    {
        $parser = Factory::createParser('codeblock');

        $this->assertInstanceOf(MarkdownCodeBlockParser::class, $parser);
    }

    public function test_create_parser_markdown_code_block_alias(): void
    {
        $parser = Factory::createParser('markdownCodeBlock');

        $this->assertInstanceOf(MarkdownCodeBlockParser::class, $parser);
    }

    public function test_create_parser_code_with_language_option(): void
    {
        $parser = Factory::createParser('code', ['language' => 'php']);

        $this->assertInstanceOf(MarkdownCodeBlockParser::class, $parser);
    }

    public function test_create_parser_codeblocks(): void
    {
        $parser = Factory::createParser('codeblocks');

        $this->assertInstanceOf(MarkdownCodeBlocksParser::class, $parser);
    }

    public function test_create_parser_markdown_code_blocks_alias(): void
    {
        $parser = Factory::createParser('markdownCodeBlocks');

        $this->assertInstanceOf(MarkdownCodeBlocksParser::class, $parser);
    }

    public function test_create_parser_enum(): void
    {
        $parser = Factory::createParser('enum', ['values' => ['a', 'b', 'c']]);

        $this->assertInstanceOf(EnumParser::class, $parser);
    }

    public function test_create_parser_enum_throws_without_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('values is required');

        Factory::createParser('enum');
    }

    public function test_create_parser_enum_with_case_sensitive(): void
    {
        $parser = Factory::createParser('enum', [
            'values' => ['a', 'b'],
            'caseSensitive' => true,
        ]);

        $this->assertInstanceOf(EnumParser::class, $parser);
    }

    public function test_create_parser_keyvalue(): void
    {
        $parser = Factory::createParser('keyvalue');

        $this->assertInstanceOf(ListToKeyValueParser::class, $parser);
    }

    public function test_create_parser_key_value_alias(): void
    {
        $parser = Factory::createParser('key-value');

        $this->assertInstanceOf(ListToKeyValueParser::class, $parser);
    }

    public function test_create_parser_keyvalue_with_options(): void
    {
        $parser = Factory::createParser('keyvalue', [
            'separator' => '=',
            'trimValues' => false,
        ]);

        $this->assertInstanceOf(ListToKeyValueParser::class, $parser);
    }

    public function test_create_parser_listjson(): void
    {
        $parser = Factory::createParser('listjson');

        $this->assertInstanceOf(ListToJsonParser::class, $parser);
    }

    public function test_create_parser_list_json_alias(): void
    {
        $parser = Factory::createParser('list-json');

        $this->assertInstanceOf(ListToJsonParser::class, $parser);
    }

    public function test_create_parser_listjson_with_options(): void
    {
        $parser = Factory::createParser('listjson', [
            'separator' => '=',
            'indentSpaces' => 4,
        ]);

        $this->assertInstanceOf(ListToJsonParser::class, $parser);
    }

    public function test_create_parser_template(): void
    {
        $parser = Factory::createParser('template');

        $this->assertInstanceOf(ReplaceStringTemplateParser::class, $parser);
    }

    public function test_create_parser_replace_alias(): void
    {
        $parser = Factory::createParser('replace');

        $this->assertInstanceOf(ReplaceStringTemplateParser::class, $parser);
    }

    public function test_create_parser_template_with_options(): void
    {
        $parser = Factory::createParser('template', [
            'strict' => true,
            'replacements' => ['key' => 'value'],
        ]);

        $this->assertInstanceOf(ReplaceStringTemplateParser::class, $parser);
    }

    public function test_create_parser_function(): void
    {
        $parser = Factory::createParser('function');

        $this->assertInstanceOf(LlmFunctionParser::class, $parser);
    }

    public function test_create_parser_tool_alias(): void
    {
        $parser = Factory::createParser('tool');

        $this->assertInstanceOf(LlmFunctionParser::class, $parser);
    }

    public function test_create_parser_function_with_wrapped_parser(): void
    {
        $wrappedParser = new JsonParser;
        $parser = Factory::createParser('function', ['parser' => $wrappedParser]);

        $this->assertInstanceOf(LlmFunctionParser::class, $parser);
    }

    public function test_create_parser_custom(): void
    {
        $handler = fn ($response) => 'custom';
        $parser = Factory::createParser('custom', ['handler' => $handler]);

        $this->assertInstanceOf(CustomParser::class, $parser);
    }

    public function test_create_parser_custom_throws_without_handler(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('handler is required');

        Factory::createParser('custom');
    }

    public function test_create_parser_throws_for_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown parser type: unknown');

        Factory::createParser('unknown');
    }

    public function test_create_core_executor(): void
    {
        $handler = fn ($input) => $input['value'] * 2;
        $executor = Factory::createCoreExecutor($handler);

        $this->assertInstanceOf(CoreExecutor::class, $executor);
    }

    public function test_create_core_executor_with_name(): void
    {
        $handler = fn ($input) => $input['value'] * 2;
        $executor = Factory::createCoreExecutor($handler, 'doubler');

        $this->assertInstanceOf(CoreExecutor::class, $executor);
    }

    public function test_create_llm_executor(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $executor = Factory::createLlmExecutor([
            'llm' => $provider,
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
        ]);

        $this->assertInstanceOf(LlmExecutor::class, $executor);
    }

    public function test_create_llm_executor_with_provider_key(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $executor = Factory::createLlmExecutor([
            'provider' => $provider,
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
        ]);

        $this->assertInstanceOf(LlmExecutor::class, $executor);
    }

    public function test_create_llm_executor_with_all_options(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $executor = Factory::createLlmExecutor([
            'llm' => $provider,
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
            'parser' => new JsonParser,
            'temperature' => 0.7,
            'maxTokens' => 1000,
            'name' => 'test-executor',
        ]);

        $this->assertInstanceOf(LlmExecutor::class, $executor);
    }

    public function test_create_llm_executor_throws_without_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('llm/provider is required');

        Factory::createLlmExecutor([
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
        ]);
    }

    public function test_create_llm_executor_throws_without_prompt(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('prompt is required');

        Factory::createLlmExecutor([
            'llm' => $provider,
            'model' => 'gpt-4',
        ]);
    }

    public function test_create_llm_executor_throws_without_model(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('model is required');

        Factory::createLlmExecutor([
            'llm' => $provider,
            'prompt' => Factory::createChatPrompt(),
        ]);
    }

    public function test_create_llm_executor_with_functions(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $tools = Factory::useExecutors([
            [
                'name' => 'calculator',
                'description' => 'Performs calculations',
                'handler' => fn ($input) => $input['a'] + $input['b'],
            ],
        ]);

        $executor = Factory::createLlmExecutorWithFunctions([
            'llm' => $provider,
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
            'tools' => $tools,
        ]);

        $this->assertInstanceOf(LlmExecutorWithFunctions::class, $executor);
    }

    public function test_create_llm_executor_with_functions_from_array(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $executor = Factory::createLlmExecutorWithFunctions([
            'llm' => $provider,
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
            'tools' => [
                [
                    'name' => 'calculator',
                    'description' => 'Performs calculations',
                    'handler' => fn ($input) => $input['a'] + $input['b'],
                ],
            ],
        ]);

        $this->assertInstanceOf(LlmExecutorWithFunctions::class, $executor);
    }

    public function test_create_llm_executor_with_functions_throws_for_invalid_tools(): void
    {
        $provider = Factory::useLlm('openai.gpt-4', [
            'apiKey' => 'test-key',
            'transport' => $this->mockTransport,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tools must be UseExecutors or array of CallableExecutor');

        Factory::createLlmExecutorWithFunctions([
            'llm' => $provider,
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
            'tools' => 'invalid',
        ]);
    }

    public function test_create_llm_executor_with_functions_throws_without_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('llm/provider is required');

        Factory::createLlmExecutorWithFunctions([
            'prompt' => Factory::createChatPrompt(),
            'model' => 'gpt-4',
            'tools' => [],
        ]);
    }

    public function test_use_executors_creates_from_callable_executors(): void
    {
        $executor1 = new CallableExecutor(
            name: 'func1',
            description: 'First function',
            handler: fn () => 'result1',
        );
        $executor2 = new CallableExecutor(
            name: 'func2',
            description: 'Second function',
            handler: fn () => 'result2',
        );

        $useExecutors = Factory::useExecutors([$executor1, $executor2]);

        $this->assertInstanceOf(UseExecutors::class, $useExecutors);
        $this->assertTrue($useExecutors->hasFunction('func1'));
        $this->assertTrue($useExecutors->hasFunction('func2'));
    }

    public function test_use_executors_creates_from_arrays(): void
    {
        $useExecutors = Factory::useExecutors([
            [
                'name' => 'add',
                'description' => 'Adds numbers',
                'handler' => fn ($input) => $input['a'] + $input['b'],
            ],
            [
                'name' => 'multiply',
                'description' => 'Multiplies numbers',
                'handler' => fn ($input) => $input['a'] * $input['b'],
            ],
        ]);

        $this->assertInstanceOf(UseExecutors::class, $useExecutors);
        $this->assertTrue($useExecutors->hasFunction('add'));
        $this->assertTrue($useExecutors->hasFunction('multiply'));
    }

    public function test_use_executors_creates_from_mixed(): void
    {
        $executor = new CallableExecutor(
            name: 'existing',
            description: 'Existing executor',
            handler: fn () => 'existing',
        );

        $useExecutors = Factory::useExecutors([
            $executor,
            [
                'name' => 'new',
                'description' => 'New executor',
                'handler' => fn () => 'new',
            ],
        ]);

        $this->assertInstanceOf(UseExecutors::class, $useExecutors);
        $this->assertTrue($useExecutors->hasFunction('existing'));
        $this->assertTrue($useExecutors->hasFunction('new'));
    }

    public function test_create_callable_executor(): void
    {
        $executor = Factory::createCallableExecutor([
            'name' => 'test_func',
            'description' => 'Test function',
            'handler' => fn ($input) => $input['value'] * 2,
        ]);

        $this->assertInstanceOf(CallableExecutor::class, $executor);
        $this->assertSame('test_func', $executor->getName());
        $this->assertSame('Test function', $executor->getDescription());
    }

    public function test_create_callable_executor_with_all_options(): void
    {
        $executor = Factory::createCallableExecutor([
            'name' => 'test_func',
            'description' => 'Test function',
            'handler' => fn ($input) => $input['value'] * 2,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'number'],
                ],
            ],
            'attributes' => ['key' => 'value'],
            'visibilityHandler' => fn () => true,
            'validateInput' => fn () => ['valid' => true, 'errors' => []],
        ]);

        $this->assertInstanceOf(CallableExecutor::class, $executor);
        $this->assertSame(['key' => 'value'], $executor->getAttributes());
    }

    public function test_create_callable_executor_throws_without_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required');

        Factory::createCallableExecutor([
            'description' => 'Test function',
            'handler' => fn () => 'result',
        ]);
    }

    public function test_create_callable_executor_throws_without_description(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('description is required');

        Factory::createCallableExecutor([
            'name' => 'test_func',
            'handler' => fn () => 'result',
        ]);
    }

    public function test_create_callable_executor_throws_without_handler(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('handler is required');

        Factory::createCallableExecutor([
            'name' => 'test_func',
            'description' => 'Test function',
        ]);
    }

    public function test_create_state(): void
    {
        $state = Factory::createState();

        $this->assertInstanceOf(ConversationState::class, $state);
    }

    public function test_create_dialogue(): void
    {
        $dialogue = Factory::createDialogue();

        $this->assertInstanceOf(Dialogue::class, $dialogue);
    }

    public function test_create_dialogue_with_name(): void
    {
        $dialogue = Factory::createDialogue('custom-dialogue');

        $this->assertInstanceOf(Dialogue::class, $dialogue);
        $this->assertSame('custom-dialogue', $dialogue->getName());
    }

    public function test_create_transport(): void
    {
        $client = $this->createMock(\Psr\Http\Client\ClientInterface::class);
        $requestFactory = $this->createMock(\Psr\Http\Message\RequestFactoryInterface::class);
        $streamFactory = $this->createMock(\Psr\Http\Message\StreamFactoryInterface::class);

        $transport = Factory::createTransport($client, $requestFactory, $streamFactory);

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }
}
