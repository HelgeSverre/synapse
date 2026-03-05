# Runtime Modules

Synapse includes optional runtime modules for production orchestration. These are framework-neutral building blocks that layer on top of executors.

## Modules

| Module                                          | Purpose                                           |
| ----------------------------------------------- | ------------------------------------------------- |
| [TraceContext & Exporters](/runtime/trace)      | Hook-based tracing and span export                |
| [Checkpoints & Memory](/runtime/memory)         | Persist run progress and conversational memory    |
| [WorkflowEngine](/runtime/workflow)             | Step orchestration with dependencies and retries  |
| [EvaluationSuite](/runtime/evaluation)          | Deterministic evals and snapshot-based regression |

## Philosophy

- Use `createExecutor()` for model calls.
- Add runtime modules only where needed.
- Keep orchestration portable across frameworks.
