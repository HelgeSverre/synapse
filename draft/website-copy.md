````# LLM-Exe PHP - Website Copy

> Draft website copy focusing on streaming capabilities, composability, and power for complex tasks

---

## Hero Section

### Hero Headline
```
Stream-First LLM Orchestration for PHP
```

### Subheadline
```
Build real-time AI experiences with composable executors,
streaming responses, and tool calling. From simple prompts
to complex agentic workflows.
```

### Hero Code Example
```php
// Real-time streaming in 10 lines
$executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4o-mini');

foreach ($executor->stream(['question' => 'Explain PHP generators']) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;  // Live tokens as they arrive
        flush();
    }
}
```

### Call to Action
- **Primary:** `composer require helgesverre/synapse` (with copy button)
- **Secondary:** "View Examples" (scroll anchor)

---

## Quick Start Section

### Headline
```
Get Started in Seconds
```

### Subheadline
```
Three steps from zero to streaming AI responses.
```

### Step 1: Install
```bash
composer require helgesverre/synapse guzzlehttp/guzzle
```

### Step 2: Configure
```php
use HelgeSverre\Synapse\Factory;
use function HelgeSverre\Synapse\useLlm;

// One-time setup
$client = new \GuzzleHttp\Client();
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory();
Factory::setDefaultTransport(
    Factory::createTransport($client, $psr17Factory, $psr17Factory)
);

// Create provider (OpenAI, Anthropic, Google, Groq, XAI...)
$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);
```

### Step 3: Stream
```php
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Streaming\TextDelta;

$prompt = (new TextPrompt)->setContent('{{question}}');
$executor = new StreamingLlmExecutor($llm, $prompt, 'gpt-4o-mini');

foreach ($executor->stream(['question' => 'What is PHP?']) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;
        flush();
    }
}
```

### Callout
```
💡 Streaming First
Unlike traditional request-response libraries, Synapse is built
for streaming from the ground up. Every feature—prompts, parsers,
tools—works seamlessly with real-time token streams.
```

---

## Core Concepts Section

### Headline
```
Powerful Primitives, Infinite Combinations
```

### Intro Copy
```
LLM-Exe is built on composable patterns that scale from simple
queries to sophisticated AI agents. Master three concepts and
build anything.
```

### Concept 1: Executors (Composability)

**Headline:** Everything is an Executor

**Copy:**
Executors are the building blocks. Chain them, nest them, compose them. Each executor takes input, does work, returns output. Simple pattern, unlimited power.

**Code Example:**
```php
// Simple executor
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
]);

// Chain executors: outline → story → refinement
function generateStory($llm, $idea) {
    $outline = generateOutline($llm, $idea);  // Executor 1
    $story = writeStory($llm, $outline);      // Executor 2
    return refineStory($llm, $story);         // Executor 3
}
```

**Key Point:**
- Wrap any function as an executor
- Compose multi-step workflows
- Each step is testable and reusable

### Concept 2: Streaming (Real-Time by Default)

**Headline:** Stream Everything, Always

**Copy:**
Forget waiting for complete responses. Stream tokens as they generate. Stream tool calls as they execute. Give users feedback instantly, not after 30 seconds of blank screen.

**Code Example:**
```php
// Stream with tool calls
$executor = new StreamingLlmExecutorWithFunctions(
    provider: $provider,
    prompt: $prompt,
    model: 'gpt-4o-mini',
    tools: $tools,
    maxIterations: 10
);

foreach ($executor->stream(['query' => 'Weather in Oslo?']) as $event) {
    match (true) {
        $event instanceof TextDelta => print($event->text),
        $event instanceof ToolCallsReady => $this->showToolExecution($event),
        $event instanceof StreamCompleted => $this->showStats($event->usage),
    };
}
```

**Key Point:**
- Streaming works with prompts, parsers, tools
- Event-based architecture (TextDelta, ToolCallsReady, StreamCompleted)
- Build responsive UIs: chat interfaces, live demos, interactive agents

### Concept 3: Tools (Give LLMs Superpowers)

**Headline:** Function Calling Made Simple

**Copy:**
LLMs are smart but isolated. Give them tools: search the web, query databases, run calculations. The LLM decides when to use them, your code defines how.

**Code Example:**
```php
$tools = useExecutors([
    [
        'name' => 'get_weather',
        'description' => 'Get current weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string'],
            ],
            'required' => ['city'],
        ],
        'handler' => fn($args) => fetchWeather($args['city']),
    ],
    [
        'name' => 'calculate',
        'description' => 'Perform mathematical calculations',
        'parameters' => [...],
        'handler' => fn($args) => evaluate($args['expression']),
    ],
]);

$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'tools' => $tools,
    'maxIterations' => 5,
]);
```

**Key Point:**
- Define tools as simple arrays or executors
- LLM orchestrates tool execution automatically
- Streaming shows tool calls in real-time

---

## Advanced Patterns Section

### Headline
```
From Prompts to Agents in Minutes
```

### Intro Copy
```
Start simple, scale to sophisticated. These patterns show what's
possible when you combine streaming, composition, and tools.
```

### Pattern 1: Self-Refinement Loops

**Copy:**
Generate output, validate it, refine if needed. Repeat until it meets your criteria. No external libraries—just executors calling executors.

**Code Snippet:**
```php
function getRefinedAnswer($llm, $question, $requiredWord, $maxAttempts = 3) {
    while ($attempt < $maxAttempts && !$meetsAllCriteria) {
        $answer = generateAnswer($llm, $question, $requiredWord);
        $validation = checkAnswer($llm, $answer, $requiredWord);

        $meetsAllCriteria = $validation['hasWord'] && $validation['underLimit'];
    }
    return $answer;
}
```

**Link:** `See full example: examples/self-refinement.php`

### Pattern 2: Sequential Composition

**Copy:**
Chain multiple LLM calls into pipelines. Output of one becomes input to the next. Build complex workflows from simple steps.

**Code Snippet:**
```php
// Outline → Story → Refinement pipeline
function generateStory($llm, $idea) {
    $outline = generateOutline($llm, $idea);     // Step 1
    $story = writeStory($llm, $outline);         // Step 2
    return $story;
}
```

**Link:** `See full example: examples/sequential-composition.php`

### Pattern 3: Agentic Workflows with Streaming

**Copy:**
Build interactive agents that think, use tools, and respond in real-time. Calculator, weather, web search, notes—all orchestrated by the LLM, all streaming live.

**Visual Demo Idea:**
```
You: "What's the weather in Tokyo? Also, what is 42 * 17?"

Agent: *streams thinking* "I'll need to check the weather and do a calculation..."
      ⚡ get_weather({"city": "Tokyo"})
      ⚡ calculate({"expression": "42 * 17"})
      "The weather in Tokyo is partly cloudy at 18°C. And 42 × 17 = 714."
```

**Code Snippet:**
```php
$tools = new UseExecutors([
    CalculatorTool::create(),
    WeatherTool::create(),
    WebSearchTool::create(),
    NotesTool::create(),
]);

$executor = new StreamingLlmExecutorWithFunctions(
    provider: $provider,
    prompt: $prompt,
    model: 'gpt-4o-mini',
    tools: $tools,
    maxIterations: 10
);

// Stream the agentic response
foreach ($executor->stream(['message' => $userInput]) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;  // Live reasoning
    }
    if ($event instanceof ToolCallsReady) {
        // Show which tools the agent is using
        foreach ($event->toolCalls as $call) {
            echo "⚡ {$call->name}(".json_encode($call->arguments).")\n";
        }
    }
}
```

**Link:** `Try it: php examples/agentic-agent-cli.php`

---

## Why LLM-Exe Section

### Headline
```
Built for Production PHP Applications
```

### Feature Grid

#### Multi-Provider Support
**Copy:** OpenAI, Anthropic, Google, Groq, XAI, Mistral, Moonshot. Switch providers with one line. Your code stays the same.

```php
// Switch from OpenAI to Anthropic in seconds
$llm = useLlm('anthropic.claude-3-sonnet', ['apiKey' => $key]);
```

#### Type-Safe & Tested
**Copy:** Full PHP 8.2+ type hints. Comprehensive test suite. PSR-4, PSR-7, PSR-17, PSR-18 compliant. Production-ready out of the box.

#### Parser System
**Copy:** Extract structured data from LLM responses. JSON schemas, booleans, enums, lists, code blocks—all validated automatically.

```php
$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'confidence' => ['type' => 'number'],
        ],
    ],
]);
```

#### Event Hooks & Observability
**Copy:** Hook into every step of the execution lifecycle. Log requests, track token usage, monitor performance, debug issues.

```php
$executor
    ->on(BeforeProviderCall::class, fn($e) => $logger->debug("Calling {$e->model}"))
    ->on(AfterProviderCall::class, fn($e) => $metrics->track($e->usage))
    ->on(OnToolCall::class, fn($e) => $logger->info("Tool: {$e->toolCall->name}"));
```

#### Conversation State
**Copy:** Built-in conversation history management. Track context, manage multi-turn dialogues, implement chat interfaces.

```php
$state = new ConversationState();
$state = $state
    ->withMessage(Message::user('Hello'))
    ->withMessage(Message::assistant('Hi there!'))
    ->withContext(new ContextItem('user_id', '12345'));
```

#### Template System
**Copy:** Powerful prompt templating with variables, helpers, partials, and nested paths. Keep prompts maintainable and reusable.

```php
$prompt->registerHelper('upper', fn($s) => strtoupper($s));
$prompt->addUserMessage('{{upper greeting}}, {{user.name}}!');
```

---

## Examples Showcase Section

### Headline
```
Learn by Example
```

### Copy
```
Explore working examples that show real-world patterns.
All examples are in the examples/ directory—copy, run, modify.
```

### Example Grid

- **basic-usage.php** - Simple prompt → response workflow
- **streaming-cli.php** - Real-time streaming demo with multiple providers
- **tool-calling.php** - Weather and calculator tools
- **agentic-agent-cli.php** - Full interactive agent with 5 tools
- **sequential-composition.php** - Chain executors into pipelines
- **self-refinement.php** - Iterative improvement loops
- **json-output.php** - Structured data extraction
- **conversation-state.php** - Multi-turn dialogue management
- **hooks-and-events.php** - Event system and observability

### Call to Action
```bash
# Clone and run
git clone https://github.com/helgesverre/synapse
cd synapse
composer install
php examples/streaming-cli.php
```

---

## Getting Started Section

### Headline
```
Ready to Build?
```

### Three Paths

#### Path 1: Quick Start
**For:** Developers who want to jump in

1. `composer require helgesverre/synapse guzzlehttp/guzzle`
2. Copy Quick Start code above
3. Run it

**Link:** [Quick Start Guide](#quick-start)

#### Path 2: Learn by Example
**For:** Developers who learn by doing

1. Clone the repo
2. Run `php examples/streaming-cli.php`
3. Explore other examples
4. Copy what you need

**Link:** [View Examples](https://github.com/helgesverre/synapse/tree/main/examples)

#### Path 3: Deep Dive
**For:** Developers who want to understand everything

1. Read the full README
2. Understand executors, prompts, parsers
3. Build complex workflows
4. Contribute back

**Link:** [Full Documentation](https://github.com/helgesverre/synapse)

---

## Footer

### Quick Links
- [GitHub Repository](https://github.com/helgesverre/synapse)
- [Packagist Package](https://packagist.org/packages/helgesverre/synapse)
- [Issue Tracker](https://github.com/helgesverre/synapse/issues)
- [Examples Directory](https://github.com/helgesverre/synapse/tree/main/examples)

### Credits
PHP adaptation of [llm-exe](https://github.com/gregreindel/llm-exe) by Greg Reindel

### License
MIT License - Free to use in commercial and open source projects

---

## Implementation Notes

### Design System Suggestions

**Colors:**
- Primary: Modern blue/purple gradient (streaming/tech vibe)
- Accent: Electric blue for CTAs and code highlights
- Background: Dark mode friendly with light mode support
- Syntax highlighting: Use a modern theme like Nord or Dracula

**Typography:**
- Headlines: Bold, sans-serif (Inter, SF Pro, or similar)
- Body: Readable sans-serif, 16-18px base size
- Code: JetBrains Mono or Fira Code with ligatures

**Interactive Elements:**
- Copy buttons on all code blocks
- Live streaming demo (if possible) showing real tokens arriving
- Animated transition between code examples
- Syntax highlighted diffs showing before/after

**Progressive Disclosure:**
1. Hero with instant "wow" (streaming code)
2. Quick start (get running in 60 seconds)
3. Core concepts (learn the patterns)
4. Advanced examples (see the power)
5. Why/features (convince stakeholders)
6. Next steps (clear path forward)

### Content Principles

1. **Code > Words** - Let examples do the talking
2. **Show, Don't Tell** - Every concept has a code example
3. **Progressive Complexity** - Start simple, reveal depth
4. **Action-Oriented** - Every section has a "try this" moment
5. **Honest** - Don't overpromise, show real capabilities

### Mobile Considerations
- Code blocks should be horizontally scrollable
- Syntax highlighting must work on mobile
- Copy buttons need to be touch-friendly
- Progressive disclosure helps with small screens

---

## Additional Content Ideas

### Testimonials Section (Future)
Once users adopt, add quotes about:
- Performance improvements
- Ease of integration
- Streaming UX improvements

### Comparison Table (Optional)
Show Synapse vs other PHP LLM libraries:
- Streaming support
- Multi-provider
- Composability
- Type safety
- Active development

### Video Demo (Future)
Screen recording showing:
1. Install
2. Run agentic-agent-cli.php
3. Ask it questions with tools
4. Show the streaming + tool execution
5. 90 seconds total````