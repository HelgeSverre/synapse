import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Synapse',
  description: 'LLM Orchestration for PHP',

  head: [
    ['link', { rel: 'icon', href: "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='80' font-style='italic' font-family='serif' fill='%23f59e0b'>S</text></svg>" }],
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['link', {
      href: 'https://fonts.googleapis.com/css2?family=Unbounded:wght@400;500;700;900&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap',
      rel: 'stylesheet'
    }],
  ],

  themeConfig: {
    siteTitle: 'Synapse',

    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'API', link: '/executors/' },
      { text: 'Cookbook', link: '/cookbook/' },
    ],

    sidebar: [
      {
        text: 'Guide',
        items: [
          { text: 'Getting Started', link: '/guide/getting-started' },
          { text: 'Configuration', link: '/guide/configuration' },
          { text: 'Architecture', link: '/guide/architecture' },
        ]
      },
      {
        text: 'Executors',
        collapsed: false,
        items: [
          { text: 'Overview', link: '/executors/' },
          { text: 'LlmExecutor', link: '/executors/llm-executor' },
          { text: 'With Functions', link: '/executors/llm-executor-with-functions' },
          { text: 'Streaming', link: '/executors/streaming-executor' },
          { text: 'Streaming + Functions', link: '/executors/streaming-executor-with-functions' },
          { text: 'CoreExecutor', link: '/executors/core-executor' },
          { text: 'CallableExecutor', link: '/executors/callable-executor' },
          { text: 'UseExecutors', link: '/executors/use-executors' },
        ]
      },
      {
        text: 'Prompts',
        collapsed: false,
        items: [
          { text: 'Overview', link: '/prompts/' },
          { text: 'ChatPrompt', link: '/prompts/chat-prompt' },
          { text: 'TextPrompt', link: '/prompts/text-prompt' },
          { text: 'Template Engine', link: '/prompts/template-engine' },
        ]
      },
      {
        text: 'Parsers',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/parsers/' },
          { text: 'String', link: '/parsers/string' },
          { text: 'JSON', link: '/parsers/json' },
          { text: 'Boolean', link: '/parsers/boolean' },
          { text: 'Number', link: '/parsers/number' },
          { text: 'List', link: '/parsers/list' },
          { text: 'Enum', link: '/parsers/enum' },
          { text: 'Code Block', link: '/parsers/code-block' },
          { text: 'Key-Value', link: '/parsers/key-value' },
          { text: 'Custom', link: '/parsers/custom' },
        ]
      },
      {
        text: 'Providers',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/providers/' },
          { text: 'OpenAI', link: '/providers/openai' },
          { text: 'Anthropic', link: '/providers/anthropic' },
          { text: 'Google / Gemini', link: '/providers/google' },
          { text: 'Mistral', link: '/providers/mistral' },
          { text: 'xAI / Grok', link: '/providers/xai' },
          { text: 'Groq', link: '/providers/groq' },
          { text: 'Moonshot', link: '/providers/moonshot' },
          { text: 'Custom Provider', link: '/providers/custom-provider' },
        ]
      },
      {
        text: 'State Management',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/state/' },
          { text: 'ConversationState', link: '/state/conversation-state' },
          { text: 'Dialogue', link: '/state/dialogue' },
          { text: 'Message & Role', link: '/state/message' },
        ]
      },
      {
        text: 'Streaming',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/streaming/' },
          { text: 'Stream Events', link: '/streaming/events' },
          { text: 'Stream Transport', link: '/streaming/transport' },
        ]
      },
      {
        text: 'Hooks & Events',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/hooks/' },
          { text: 'Event Types', link: '/hooks/events' },
          { text: 'HookDispatcher', link: '/hooks/hook-dispatcher' },
        ]
      },
      {
        text: 'Tool Calling',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/tools/' },
          { text: 'Defining Tools', link: '/tools/defining-tools' },
          { text: 'Multi-Turn Loops', link: '/tools/multi-turn' },
          { text: 'Streaming with Tools', link: '/tools/streaming-tools' },
        ]
      },
      {
        text: 'Embeddings',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/embeddings/' },
          { text: 'OpenAI', link: '/embeddings/openai' },
          { text: 'Mistral', link: '/embeddings/mistral' },
          { text: 'Jina', link: '/embeddings/jina' },
          { text: 'Cohere', link: '/embeddings/cohere' },
          { text: 'Voyage', link: '/embeddings/voyage' },
        ]
      },
      {
        text: 'HTTP Transport',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/http/' },
          { text: 'PSR-18 Transport', link: '/http/psr18' },
          { text: 'Guzzle Stream Transport', link: '/http/guzzle-stream' },
        ]
      },
      {
        text: 'Cookbook',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/cookbook/' },
          { text: 'Data Extraction', link: '/cookbook/extraction' },
          { text: 'Classification', link: '/cookbook/classification' },
          { text: 'Chaining Executors', link: '/cookbook/pipelines' },
          { text: 'Self-Refinement', link: '/cookbook/self-refinement' },
          { text: 'Building Agents', link: '/cookbook/agents' },
          { text: 'RAG Patterns', link: '/cookbook/rag' },
          { text: 'LLM Validation', link: '/cookbook/validation' },
          { text: 'Code Generation', link: '/cookbook/code-generation' },
          { text: 'Human-in-the-Loop', link: '/cookbook/human-in-the-loop' },
        ]
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/HelgeSverre/synapse' }
    ],

    editLink: {
      pattern: 'https://github.com/HelgeSverre/synapse/edit/main/website/:path',
      text: 'Edit this page on GitHub'
    },

    search: {
      provider: 'local'
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Built by Helge Sverre'
    }
  },
})
