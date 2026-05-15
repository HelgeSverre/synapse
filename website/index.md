---
layout: home

hero:
  name: "Synapse"
  text: "LLM Orchestration for PHP"
  tagline: Build reliable AI pipelines in PHP with structured parsing, streaming, tools, workflows, and runtime observability.
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: API Reference
      link: /executors/
    - theme: alt
      text: GitHub
      link: https://github.com/HelgeSverre/synapse

features:
  - title: One Constructor
    details: Use createExecutor() and let Synapse select plain, streaming, tool-enabled, or streaming+tools executors.
    link: /executors/
  - title: Tool Calling Loops
    details: Register tools once, run iterative function-calling loops with limits, and inspect tool execution events.
    link: /tools/
  - title: Streaming Events
    details: Token deltas, tool-call readiness, and completion events for terminal UIs, websockets, and live dashboards.
    link: /streaming/
  - title: Runtime Modules
    details: Trace spans, checkpoints, memory stores, workflow steps, and evaluation suites for production-grade orchestration.
    link: /runtime/
  - title: Structured Output
    details: Parse and validate JSON, enums, booleans, lists, key-values, code blocks, and custom result formats.
    link: /parsers/
  - title: Composable Core
    details: Use CoreExecutor and CallableExecutor to mix deterministic business logic with LLM-powered reasoning.
    link: /executors/core-executor
---

<div class="syn-home">

## Build Useful Systems, Not Prompt Scripts

Synapse is for workloads where single calls are not enough:

- multi-step agent tasks with tool calls and retries
- structured extraction with validation guarantees
- runtime visibility for debugging and incident response
- deterministic regression checks via evaluation suites

<div class="syn-home-grid">
  <div class="syn-panel">
    <p class="syn-kicker">Fast Start</p>
    <h3>Install</h3>
    <pre><code>composer require helgesverre/synapse</code></pre>
  </div>
  <div class="syn-panel">
    <p class="syn-kicker">Canonical API</p>
    <h3>Run</h3>
    <pre><code>$executor = createExecutor([...]);
$result = $executor-&gt;run(['question' =&gt; 'What is PHP?']);</code></pre>
  </div>
  <div class="syn-panel">
    <p class="syn-kicker">Observability</p>
    <h3>Trace</h3>
    <pre><code>$bridge = createTraceBridge($exporter);
$bridge-&gt;register($executor-&gt;getHooks());</code></pre>
  </div>
</div>

## When To Use WorkflowEngine

Use `createWorkflowEngine()` when your flow needs explicit dependencies, conditional execution, retries, or partial-failure policy.

<div class="syn-workflow-points">
  <div class="syn-point">
    <h4>Use WorkflowEngine</h4>
    <p>Branching, dependent steps, resumability, or per-step retry/skip behavior.</p>
  </div>
  <div class="syn-point">
    <h4>Use createExecutor</h4>
    <p>Single-shot generation or straightforward tool loop without orchestration state.</p>
  </div>
</div>

## Runtime Stack You Can Demo Offline

All of this works with local examples and no external services:

- [Workflow engine demo](/runtime/workflow): dependency + retry orchestration
- [Checkpoints and memory](/runtime/memory): resumable run state and short-term memory
- [Trace bridge](/runtime/trace): provider/tool/run spans from hook events
- [Evaluation suite](/runtime/evaluation): expected output and snapshot regression checks

## Recommended Next Reads

- [Getting Started](/guide/getting-started)
- [Executor Overview](/executors/)
- [Tool Calling](/tools/)
- [Runtime Modules](/runtime/)

</div>
