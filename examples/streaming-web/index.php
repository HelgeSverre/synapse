<?php

declare(strict_types=1);

/**
 * Web Chat UI with Streaming
 *
 * A simple web-based chat interface that streams LLM responses in real-time.
 *
 * Usage:
 *   cd examples/streaming-web
 *   php -S localhost:8080
 *
 * Then open http://localhost:8080 in your browser.
 *
 * Environment variables:
 *   OPENAI_API_KEY, ANTHROPIC_API_KEY, GOOGLE_API_KEY, GROQ_API_KEY,
 *   XAI_API_KEY, MISTRAL_API_KEY, MOONSHOT_API_KEY
 */

require_once __DIR__.'/../../vendor/autoload.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Google\GoogleProvider;
use HelgeSverre\Synapse\Provider\Groq\GroqProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Mistral\MistralProvider;
use HelgeSverre\Synapse\Provider\Moonshot\MoonshotProvider;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\XAI\XAIProvider;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;

// Serve the HTML page for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ! isset($_GET['api'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLM Chat Widget</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-canvas: #fafafa;
            --bg-widget: #ffffff;
            --bg-surface: #f7f7f7;
            --text-primary: #18181b;
            --text-secondary: #71717a;
            --text-tertiary: #a1a1aa;
            --accent: #0066ff;
            --accent-hover: #0052cc;
            --accent-light: #e6f2ff;
            --border: #e4e4e7;
            --border-focus: #d4d4d8;
            --success: #16a34a;
            --success-light: #dcfce7;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-canvas);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            line-height: 1.5;
        }

        .widget-container {
            width: 100%;
            max-width: 800px;
            height: 720px;
            background: var(--bg-widget);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg-widget);
            flex-shrink: 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        header h1 {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--text-primary);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .provider-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .provider-pill {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--bg-surface);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            letter-spacing: -0.01em;
        }

        .provider-pill:hover {
            color: var(--text-primary);
            background: var(--bg-widget);
            border-color: var(--border);
        }

        .provider-pill.active {
            color: var(--accent);
            background: var(--accent-light);
            border-color: var(--accent);
        }

        #chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        #chat-container::-webkit-scrollbar {
            width: 8px;
        }

        #chat-container::-webkit-scrollbar-track {
            background: transparent;
        }

        #chat-container::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        #chat-container::-webkit-scrollbar-thumb:hover {
            background: var(--border-focus);
        }

        .message {
            max-width: 85%;
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            align-self: flex-end;
        }

        .message.assistant {
            align-self: flex-start;
        }

        .message-content {
            padding: 0.875rem 1.125rem;
            line-height: 1.6;
            font-size: 0.9375rem;
            letter-spacing: -0.01em;
        }

        .message.user .message-content {
            background: var(--text-primary);
            color: white;
        }

        .message.assistant .message-content {
            background: var(--bg-surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .message.assistant.streaming .message-content {
            border-left: 2px solid var(--success);
        }

        .message-content code {
            background: rgba(0, 0, 0, 0.06);
            padding: 0.125rem 0.375rem;
            font-family: 'SF Mono', 'Monaco', 'Cascadia Code', monospace;
            font-size: 0.875em;
        }

        .message.user .message-content code {
            background: rgba(255, 255, 255, 0.15);
        }

        .welcome {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
            max-width: 480px;
            margin: auto;
        }

        .welcome h2 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .welcome p {
            font-size: 0.9375rem;
            line-height: 1.6;
        }

        .status-bar {
            display: flex;
            gap: 1.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.6875rem;
            color: var(--text-tertiary);
            background: var(--bg-surface);
            border-top: 1px solid var(--border);
            font-weight: 500;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .status-bar span {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        #input-container {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--bg-widget);
            flex-shrink: 0;
        }

        #input-form {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        #message-input {
            flex: 1;
            background: var(--bg-surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
            font-family: inherit;
            outline: none;
            transition: all 0.15s ease;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            letter-spacing: -0.01em;
        }

        #message-input:focus {
            background: var(--bg-widget);
            border-color: var(--border-focus);
        }

        #message-input::placeholder {
            color: var(--text-tertiary);
        }

        #send-btn {
            background: var(--text-primary);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s ease;
            letter-spacing: -0.01em;
            height: 44px;
        }

        #send-btn:hover:not(:disabled) {
            background: var(--text-secondary);
        }

        #send-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        @media (max-width: 640px) {
            body {
                padding: 0;
            }

            .widget-container {
                height: 100vh;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="widget-container">
        <header>
            <div class="header-content">
                <h1>Chat</h1>
                <div class="status-indicator">
                    <div class="status-dot"></div>
                    <span id="connection-status">Ready</span>
                </div>
            </div>
            <div class="provider-pills" id="provider-pills">
                <button class="provider-pill active" data-provider="openai">OpenAI</button>
                <button class="provider-pill" data-provider="anthropic">Anthropic</button>
                <button class="provider-pill" data-provider="google">Google</button>
                <button class="provider-pill" data-provider="groq">Groq</button>
                <button class="provider-pill" data-provider="xai">xAI</button>
                <button class="provider-pill" data-provider="mistral">Mistral</button>
                <button class="provider-pill" data-provider="moonshot">Moonshot</button>
            </div>
        </header>

        <div id="chat-container">
            <div class="welcome">
                <h2>Start a conversation</h2>
                <p>Choose a provider above and begin chatting. Messages stream in real-time.</p>
            </div>
        </div>

        <div class="status-bar">
            <span id="message-count">Messages · 0</span>
            <span id="token-count">Tokens · 0</span>
        </div>

        <div id="input-container">
            <form id="input-form">
                <textarea id="message-input" placeholder="Type a message..." autocomplete="off" rows="1"></textarea>
                <button type="submit" id="send-btn">Send</button>
            </form>
        </div>
    </div>

    <script>
        const chatContainer = document.getElementById('chat-container');
        const inputForm = document.getElementById('input-form');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const providerPills = document.getElementById('provider-pills');
        const tokenCountEl = document.getElementById('token-count');
        const messageCountEl = document.getElementById('message-count');
        const connectionStatus = document.getElementById('connection-status');

        let conversationHistory = [];
        let totalTokens = 0;
        let isStreaming = false;
        let currentProvider = 'openai';

        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Provider selection
        providerPills.addEventListener('click', (e) => {
            if (e.target.classList.contains('provider-pill')) {
                document.querySelectorAll('.provider-pill').forEach(pill => {
                    pill.classList.remove('active');
                });
                e.target.classList.add('active');
                currentProvider = e.target.dataset.provider;
            }
        });

        function clearWelcome() {
            const welcome = chatContainer.querySelector('.welcome');
            if (welcome) {
                welcome.style.animation = 'fadeOut 0.2s ease';
                setTimeout(() => welcome.remove(), 200);
            }
        }

        function addMessage(role, content, isStreaming = false) {
            clearWelcome();
            const div = document.createElement('div');
            div.className = `message ${role}${isStreaming ? ' streaming' : ''}`;
            div.innerHTML = `
                <div class="message-content">${escapeHtml(content)}</div>
            `;
            chatContainer.appendChild(div);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            return div;
        }

        function updateMessage(messageEl, content, finished = false) {
            const contentEl = messageEl.querySelector('.message-content');
            contentEl.innerHTML = escapeHtml(content);
            if (finished) {
                messageEl.classList.remove('streaming');
            }
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function updateStats() {
            messageCountEl.textContent = `Messages · ${conversationHistory.length}`;
            tokenCountEl.textContent = `Tokens · ${totalTokens.toLocaleString()}`;
        }

        inputForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message || isStreaming) return;

            // Add user message
            addMessage('user', message);
            conversationHistory.push({ role: 'user', content: message });
            messageInput.value = '';
            messageInput.style.height = 'auto';
            updateStats();

            // Create assistant message placeholder
            const assistantDiv = addMessage('assistant', '', true);
            isStreaming = true;
            sendBtn.disabled = true;
            connectionStatus.textContent = 'Streaming...';

            try {
                const response = await fetch('?api=chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        provider: currentProvider,
                        messages: conversationHistory
                    })
                });

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let assistantContent = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);
                            if (data === '[DONE]') continue;

                            try {
                                const json = JSON.parse(data);
                                if (json.text) {
                                    assistantContent += json.text;
                                    updateMessage(assistantDiv, assistantContent);
                                }
                                if (json.usage) {
                                    totalTokens += (json.usage.input || 0) + (json.usage.output || 0);
                                }
                            } catch (e) {
                                // Ignore parse errors for partial chunks
                            }
                        }
                    }
                }

                // Finalize
                updateMessage(assistantDiv, assistantContent, true);
                conversationHistory.push({ role: 'assistant', content: assistantContent });
                updateStats();

            } catch (error) {
                updateMessage(assistantDiv, `Error: ${error.message}`, true);
                assistantDiv.querySelector('.message-content').style.borderColor = '#dc2626';
            } finally {
                isStreaming = false;
                sendBtn.disabled = false;
                connectionStatus.textContent = 'Ready';
                messageInput.focus();
            }
        });

        // Focus input on load
        messageInput.focus();
    </script>
</body>
</html>
HTML;
    exit;
}

// API endpoint for chat (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api']) && $_GET['api'] === 'chat') {
    // Disable output buffering for streaming
    while (ob_get_level()) {
        ob_end_clean();
    }
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', 'off');

    // Set streaming headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Disable nginx buffering

    // Parse request
    $input = json_decode(file_get_contents('php://input'), true);
    $providerName = $input['provider'] ?? 'openai';
    $messageHistory = $input['messages'] ?? [];

    // Create provider
    $transport = new GuzzleStreamTransport(new Client(['timeout' => 120]));

    try {
        [$provider, $model] = match ($providerName) {
            'openai' => [
                new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: throw new Exception('OPENAI_API_KEY not set')),
                'gpt-4o-mini',
            ],
            'anthropic' => [
                new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: throw new Exception('ANTHROPIC_API_KEY not set')),
                'claude-3-haiku-20240307',
            ],
            'google' => [
                new GoogleProvider($transport, getenv('GOOGLE_API_KEY') ?: throw new Exception('GOOGLE_API_KEY not set')),
                'gemini-2.0-flash-exp',
            ],
            'groq' => [
                new GroqProvider($transport, getenv('GROQ_API_KEY') ?: throw new Exception('GROQ_API_KEY not set')),
                'llama-3.3-70b-versatile',
            ],
            'xai' => [
                new XAIProvider($transport, getenv('XAI_API_KEY') ?: throw new Exception('XAI_API_KEY not set')),
                'grok-beta',
            ],
            'mistral' => [
                new MistralProvider($transport, getenv('MISTRAL_API_KEY') ?: throw new Exception('MISTRAL_API_KEY not set')),
                'mistral-small-latest',
            ],
            'moonshot' => [
                new MoonshotProvider($transport, getenv('MOONSHOT_API_KEY') ?: throw new Exception('MOONSHOT_API_KEY not set')),
                'moonshot-v1-8k',
            ],
            default => throw new Exception("Unknown provider: {$providerName}"),
        };
    } catch (Exception $e) {
        echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
        flush();
        exit;
    }

    // Convert message history to Message objects
    $messages = [];
    foreach ($messageHistory as $msg) {
        $messages[] = match ($msg['role']) {
            'user' => Message::user($msg['content']),
            'assistant' => Message::assistant($msg['content']),
            default => Message::user($msg['content']),
        };
    }

    // Create request
    $request = new GenerationRequest(
        model: $model,
        messages: $messages,
        maxTokens: 1024,
        systemPrompt: 'You are a helpful, friendly assistant. Be concise but informative.',
    );

    // Stream response
    try {
        foreach ($provider->stream($request) as $event) {
            if (connection_aborted()) {
                break;
            }

            if ($event instanceof TextDelta) {
                echo 'data: '.json_encode(['text' => $event->text])."\n\n";
                flush();
            }

            if ($event instanceof StreamCompleted && $event->usage) {
                echo 'data: '.json_encode([
                    'usage' => [
                        'input' => $event->usage->inputTokens,
                        'output' => $event->usage->outputTokens,
                    ],
                ])."\n\n";
                flush();
            }
        }
    } catch (Throwable $e) {
        echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
        flush();
    }

    echo "data: [DONE]\n\n";
    flush();
    exit;
}

// Invalid request
http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
