# Advanced Agent Patterns

Beyond a single LLM with a flat tool registry, real systems need orchestration. This page covers three patterns that ship as runnable examples in [`examples/`](https://github.com/HelgeSverre/synapse/tree/main/examples), plus a "run-it-all-locally" recipe using Ollama.

## Multi-Agent Routing

A **manager agent** holds the conversation; specialist sub-agents do the actual work. The manager has exactly one tool — `delegate(agent, task)` — and re-yields the sub-agent's streaming events so nothing is hidden.

**Why:** Each specialist gets a focused system prompt and its own toolset. The manager doesn't need to know how to review code AND audit security AND do research — it just decides who.

```php
// Minimal sketch — full version in examples/router-agent-cli.php
use HelgeSverre\Synapse\Examples\RouterAgent\{AgentRegistry, DelegateTool};

$registry = AgentRegistry::withDefaults();   // code_reviewer, security_auditor, researcher, documenter
$delegate = new DelegateTool($provider, $model, $registry, $onAgentEvent);

$managerTools = new ToolRegistry([$delegate->create()]);
// Run the manager as a normal StreamingLlmExecutorWithFunctions
```

The `AgentRunner` inside `DelegateTool` runs each specialist as its own `StreamingLlmExecutorWithFunctions`, re-emitting its `TextDelta` / `ToolCallsReady` events so the host UI can render them in real time. The manager gets the specialist's final text back as the tool result and uses it to compose the answer.

**Pattern boundaries**
- Use multiple specialists for tasks needing different perspectives (review + security audit).
- Don't add hidden state between specialists — pass everything through `delegate`'s task string.

> Example: [`examples/router-agent-cli.php`](https://github.com/HelgeSverre/synapse/blob/main/examples/router-agent-cli.php)

## RAG with Multi-Hop Retrieval and Citations

Two tools, one agent: `search_knowledge(query)` embeds the query and returns the top-k chunks with stable IDs; `get_document(id)` fetches a chunk's full text. The agent decides when to search again, when to drill into a specific document, and when it has enough to answer.

```php
// Full version in examples/rag-agent-cli.php
use HelgeSverre\Synapse\Examples\RagAgent\{VectorStore, RagTools, SampleKnowledgeBase};

$embeddings = useEmbeddings('openai');                 // or 'ollama' for local
$store = new VectorStore($embeddings, 'text-embedding-3-small');
SampleKnowledgeBase::loadInto($store);

$tools = (new RagTools($store))->createRegistry();
// Plug into createLlmExecutorWithFunctions / streaming variant
```

**Citations**: every chunk carries a stable ID like `refund_policy_c0`. The system prompt instructs the agent to cite IDs inline; the CLI prints a "Sources" footer with the document path for each cited ID.

**Multi-hop**: because `search_knowledge` and `get_document` are just normal tools, the executor's iteration loop handles multi-step retrieval for free — the agent can search broad, then drill into a specific doc, then search again with a refined query.

**Pattern boundaries**
- `VectorStore::search()` here is in-memory cosine similarity. For >10k chunks, swap in pgvector / Qdrant — the tool surface stays the same.
- Use sensible chunk sizes (~500-1000 chars) with overlap. `Document::chunk()` enforces a `max(1, chunkSize - overlap)` step so misconfiguration won't infinite-loop.

> Example: [`examples/rag-agent-cli.php`](https://github.com/HelgeSverre/synapse/blob/main/examples/rag-agent-cli.php)

## Task Decomposition with DAG Execution

Two phases:

1. **Planning** — the agent is given a single tool `submit_plan(steps[])` whose JSON schema forces structured output. A `PlanValidator` checks for missing dependencies, cycles, and duplicate IDs before the plan is accepted.
2. **Execution** — `PlanExecutor` topologically sorts the steps and runs each one through a worker executor, tracking per-step state. On failure, the plan can be re-submitted with `previousFailure` context.

```php
// Full version in examples/task-decomposer-cli.php
use HelgeSverre\Synapse\Examples\TaskDecomposer\{SubmitPlanTool, PlanExecutor};

$submitPlanTool = new SubmitPlanTool();
$planningTools = new ToolRegistry([$submitPlanTool->create()]);

// Phase 1: plan
$planner->run(/* goal */);
$plan = $submitPlanTool->getLastValidPlan();

// Phase 2: execute
$executor = new PlanExecutor($workerProvider, $workerModel, $transport, $onStepEvent);
$executor->execute($plan);
```

**Why force structured output through a tool?** A `submit_plan` tool with a strict schema is more reliable than asking for JSON in the response. The model can talk freely, then commit a plan exactly once via the tool. Validation rejects malformed plans; the executor only runs valid DAGs.

**Pattern boundaries**
- Keep the worker prompt focused on executing a single step — pass step description, dependencies' outputs, and nothing else.
- DAG over linear lists: parallel branches and shared dependencies fall out naturally.

> Example: [`examples/task-decomposer-cli.php`](https://github.com/HelgeSverre/synapse/blob/main/examples/task-decomposer-cli.php)

## Running the Whole Stack Locally with Ollama

All three examples above accept `ollama` as the provider, so the entire stack — chat, tools, embeddings — runs without an API key:

```bash
# Prereqs (once)
ollama serve
ollama pull gemma4:latest
ollama pull granite-embedding:latest   # only needed for the RAG example

# Run each example
php examples/router-agent-cli.php ollama
EMBEDDING_PROVIDER=ollama php examples/rag-agent-cli.php ollama
php examples/task-decomposer-cli.php ollama
```

**Verified live runs** (Synapse uses Ollama's OpenAI-compatible endpoint at `http://localhost:11434/v1`):

| Example | Prompt | What happened |
| --- | --- | --- |
| router-agent | "Audit this code for SQL injection: ..." | Manager delegated to `security_auditor`, which produced a full vulnerability analysis with remediation. |
| rag-agent | "What is the refund policy for digital products?" | Embedded the query via `granite-embedding:latest`, retrieved the right chunks, answered with `[refund_policy_c0]` citations. |
| task-decomposer | "Make a peanut butter and jelly sandwich" | Planner produced a validated 5-step DAG (gather → spread × 2 → assemble → serve); executor ran all five in dependency order. |

Override the model per-run with `OLLAMA_MODEL=qwen3.6:latest` if you want a stronger tool-caller. The provider sends `tools`/`tool_choice` through the OpenAI-compatible adapter, so anything Ollama exposes works.

## Related

- [Building Agents](/cookbook/agents) — the underlying tool-calling pattern.
- [RAG Patterns](/cookbook/rag) — primer on embed → search → generate.
- [Human-in-the-Loop](/cookbook/human-in-the-loop) — pause for approval before risky tool calls.
- [Ollama provider](/providers/ollama) and [Ollama embeddings](/embeddings/ollama).
