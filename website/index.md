---
layout: home

hero:
  name: "Synapse"
  text: "LLM Orchestration for PHP"
  tagline: Structured output, streaming, tool calling, and agentic workflows — with any provider.
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/HelgeSverre/synapse

features:
  - title: Executor Pattern
    details: Composable execution pipeline — Prompt, Provider, Parser, Result — with lifecycle hooks at every stage.
    link: /executors/
  - title: Prompt System
    details: Template-based prompts with Handlebars-style syntax, helpers, partials, and conversation history management.
    link: /prompts/
  - title: Parser System
    details: 12+ parsers for extracting structured data — JSON with schema validation, booleans, enums, lists, code blocks.
    link: /parsers/
  - title: Tool Calling
    details: Built-in support for multi-step tool calling with automatic execution loops and streaming tool calls.
    link: /tools/
  - title: Streaming
    details: Token streaming with event-driven generators. Build chat UIs and live demos with real-time output.
    link: /streaming/
  - title: Multi-Provider
    details: OpenAI, Anthropic, Google Gemini, Mistral, xAI — switch providers by changing one line of code.
    link: /providers/
---
