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
 *   OPENAI_API_KEY, ANTHROPIC_API_KEY, MISTRAL_API_KEY, MOONSHOT_API_KEY
 */

require_once __DIR__.'/../../vendor/autoload.php';

use GuzzleHttp\Client;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\Mistral\MistralProvider;
use LlmExe\Provider\Moonshot\MoonshotProvider;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;

// Serve the HTML page for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ! isset($_GET['api'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLM Streaming Chat</title>
    <style>
        :root {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --bg-tertiary: #0f3460;
            --accent: #e94560;
            --accent-hover: #ff6b6b;
            --text-primary: #eee;
            --text-secondary: #aaa;
            --user-msg-bg: #0f3460;
            --assistant-msg-bg: #1a1a2e;
            --border-color: #333;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: var(--bg-secondary);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        header h1::before {
            content: '🤖';
        }

        .provider-select {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .provider-select label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .provider-select select {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
        }

        #chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 80%;
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            line-height: 1.6;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.user {
            background: var(--user-msg-bg);
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
        }

        .message.assistant {
            background: var(--assistant-msg-bg);
            border: 1px solid var(--border-color);
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
        }

        .message.assistant.streaming {
            border-color: var(--accent);
        }

        .message .role {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .message .content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .message .content code {
            background: rgba(0,0,0,0.3);
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.9em;
        }

        .cursor {
            display: inline-block;
            width: 2px;
            height: 1.1em;
            background: var(--accent);
            margin-left: 2px;
            animation: blink 1s infinite;
            vertical-align: text-bottom;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        .typing-indicator {
            color: var(--text-secondary);
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
        }

        #input-container {
            background: var(--bg-secondary);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        #input-form {
            display: flex;
            gap: 0.75rem;
            max-width: 900px;
            margin: 0 auto;
        }

        #message-input {
            flex: 1;
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.875rem 1.25rem;
            border-radius: 1.5rem;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
        }

        #message-input:focus {
            border-color: var(--accent);
        }

        #message-input::placeholder {
            color: var(--text-secondary);
        }

        #send-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        #send-btn:hover:not(:disabled) {
            background: var(--accent-hover);
        }

        #send-btn:active:not(:disabled) {
            transform: scale(0.98);
        }

        #send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-bar {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: var(--bg-secondary);
        }

        .welcome {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .welcome h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .welcome p {
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <header>
        <h1>LLM Streaming Chat</h1>
        <div class="provider-select">
            <label for="provider">Provider:</label>
            <select id="provider">
                <option value="openai">OpenAI (GPT-4o Mini)</option>
                <option value="anthropic">Anthropic (Claude 3 Haiku)</option>
                <option value="mistral">Mistral (Small)</option>
                <option value="moonshot">Moonshot (Kimi)</option>
            </select>
        </div>
    </header>

    <div id="chat-container">
        <div class="welcome">
            <h2>Welcome to LLM Streaming Chat</h2>
            <p>Select a provider above and start chatting. Responses stream in real-time as they're generated.</p>
        </div>
    </div>

    <div class="status-bar">
        <span id="token-count">Tokens: 0</span>
        <span id="message-count">Messages: 0</span>
    </div>

    <div id="input-container">
        <form id="input-form">
            <input type="text" id="message-input" placeholder="Type your message..." autocomplete="off">
            <button type="submit" id="send-btn">Send</button>
        </form>
    </div>

    <script>
        const chatContainer = document.getElementById('chat-container');
        const inputForm = document.getElementById('input-form');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const providerSelect = document.getElementById('provider');
        const tokenCountEl = document.getElementById('token-count');
        const messageCountEl = document.getElementById('message-count');

        let conversationHistory = [];
        let totalTokens = 0;
        let isStreaming = false;

        function clearWelcome() {
            const welcome = chatContainer.querySelector('.welcome');
            if (welcome) welcome.remove();
        }

        function addMessage(role, content, isStreaming = false) {
            clearWelcome();
            const div = document.createElement('div');
            div.className = `message ${role}${isStreaming ? ' streaming' : ''}`;
            div.innerHTML = `
                <div class="role">${role}</div>
                <div class="content">${escapeHtml(content)}${isStreaming ? '<span class="cursor"></span>' : ''}</div>
            `;
            chatContainer.appendChild(div);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            return div;
        }

        function updateMessage(messageEl, content, finished = false) {
            const contentEl = messageEl.querySelector('.content');
            contentEl.innerHTML = escapeHtml(content) + (finished ? '' : '<span class="cursor"></span>');
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
            messageCountEl.textContent = `Messages: ${conversationHistory.length}`;
            tokenCountEl.textContent = `Tokens: ${totalTokens}`;
        }

        inputForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message || isStreaming) return;

            // Add user message
            addMessage('user', message);
            conversationHistory.push({ role: 'user', content: message });
            messageInput.value = '';
            updateStats();

            // Create assistant message placeholder
            const assistantDiv = addMessage('assistant', '', true);
            isStreaming = true;
            sendBtn.disabled = true;

            try {
                const response = await fetch('?api=chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        provider: providerSelect.value,
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
                assistantDiv.style.borderColor = '#e94560';
            } finally {
                isStreaming = false;
                sendBtn.disabled = false;
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
