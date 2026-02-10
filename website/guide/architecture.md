# Architecture

## The Executor Pipeline

Every LLM interaction in Synapse flows through the same pipeline:

```
┌─────────────────────────────────────────────────────────────┐
│                        Executor                              │
│  ┌─────────┐    ┌──────────┐    ┌────────┐    ┌─────────┐  │
│  │ Prompt  │ -> │ Provider │ -> │ Parser │ -> │ Result  │  │
│  └─────────┘    └──────────┘    └────────┘    └─────────┘  │
│       ↑              ↑              ↑                       │
│       │              │              │                       │
│   Template       HTTP Call      Extract                     │
│   Rendering      to LLM API     Structured                  │
│                                 Data                        │
└─────────────────────────────────────────────────────────────┘
```

1. **Prompt** renders template variables into messages
2. **Provider** sends the messages to an LLM API over HTTP
3. **Parser** extracts structured data from the response text
4. **Result** wraps the parsed value with state and metadata

## Core Concepts

### Executors

Executors orchestrate the pipeline. Each executor type serves a different use case:

| Executor | Use Case |
|----------|----------|
| [LlmExecutor](/executors/llm-executor) | Standard LLM call: prompt → response → parsed result |
| [LlmExecutorWithFunctions](/executors/llm-executor-with-functions) | LLM with tool calling — automatic multi-turn loop |
| [StreamingLlmExecutor](/executors/streaming-executor) | Real-time token streaming via generators |
| [StreamingLlmExecutorWithFunctions](/executors/streaming-executor-with-functions) | Streaming with tool calling |
| [CoreExecutor](/executors/core-executor) | Wrap a plain PHP function as an executor |
| [CallableExecutor](/executors/callable-executor) | Define a tool/function for LLM tool calling |
| [UseExecutors](/executors/use-executors) | Registry of multiple tools |

### Prompts

[Prompts](/prompts/) define what gets sent to the LLM. Two types:

- **ChatPrompt** — structured messages (system, user, assistant, tool) with template variables
- **TextPrompt** — a single text string with template variables

Template syntax uses <code v-pre>{{variable}}</code> notation with support for nested paths, helpers, and partials.

### Parsers

[Parsers](/parsers/) transform raw LLM text into typed PHP values. There are 12+ built-in parsers for common formats: strings, JSON, booleans, numbers, lists, enums, code blocks, and more.

### Providers

[Providers](/providers/) handle the HTTP communication with LLM APIs. Synapse includes providers for OpenAI, Anthropic, Google Gemini, Mistral, and xAI. All providers implement `LlmProviderInterface`.

### State

[State](/state/) tracks conversation history and context. `ConversationState` is immutable (functional style), while `Dialogue` offers a mutable fluent API for multi-turn conversations.

### Hooks

[Hooks](/hooks/) provide lifecycle events at each stage of the pipeline: before/after prompt rendering, before/after provider calls, on success, on error, and streaming events. Use them for logging, metrics, and debugging.

## Design Principles

- **Composable** — mix and match prompts, parsers, and providers freely
- **Provider-agnostic** — switch LLM providers without changing application code
- **PSR-compliant** — uses PSR-7, PSR-17, and PSR-18 for HTTP
- **Type-safe** — full PHP 8.2 typing with generics in docblocks
- **Immutable state** — `ConversationState` uses `withX()` patterns
- **No framework dependency** — works in any PHP application

## Execution Flow

Here's what happens when you call `$executor->execute($input)`:

1. `BaseExecutor::execute()` starts timing, transforms input via `getHandlerInput()`, and calls `handler()`
2. `LlmExecutor::handler()` dispatches `BeforePromptRender` event
3. The prompt's `render($input)` replaces template variables
4. `AfterPromptRender` event fires with rendered messages
5. A `GenerationRequest` is built with model, messages, and options
6. `BeforeProviderCall` fires, then the provider's `generate()` makes the HTTP call
7. `AfterProviderCall` fires with the response
8. The parser's `parse()` extracts structured data from the response
9. State is updated with the assistant's message
10. An `ExecutionResult` is returned with the parsed value, state, and raw response
11. `OnSuccess` and `OnComplete` events fire

For tool calling, steps 5-8 loop: if the LLM returns tool calls instead of text, the tools are executed, results are added as messages, and the LLM is called again. This repeats until the LLM responds with text or `maxIterations` is reached.
