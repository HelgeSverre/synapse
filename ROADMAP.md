# Roadmap

## Planned Features

### JSONL-Backed Dialogue Persistence Driver

A file-based persistence driver for `Dialogue` state using JSONL (JSON Lines) format.

#### Motivation
- Simple, human-readable format for debugging
- Append-only writes (no need to rewrite entire file)
- Easy to stream/tail for monitoring
- Git-friendly (line-based diffs)
- No external dependencies (SQLite, Redis, etc.)

#### Proposed Interface

```php
interface DialoguePersistenceInterface
{
    public function save(Dialogue $dialogue): void;
    public function load(string $name): Dialogue;
    public function exists(string $name): bool;
    public function delete(string $name): void;
    public function list(): array; // Returns available dialogue names
}
```

#### JSONL Driver Implementation Sketch

```php
final class JsonlDialoguePersistence implements DialoguePersistenceInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly string $extension = '.jsonl',
    ) {}

    public function save(Dialogue $dialogue): void
    {
        $path = $this->getPath($dialogue->getName());
        $handle = fopen($path, 'w');
        
        foreach ($dialogue->getHistory() as $message) {
            fwrite($handle, json_encode($message->toArray()) . "\n");
        }
        
        fclose($handle);
    }

    public function append(Dialogue $dialogue, Message $message): void
    {
        $path = $this->getPath($dialogue->getName());
        file_put_contents(
            $path,
            json_encode($message->toArray()) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public function load(string $name): Dialogue
    {
        $path = $this->getPath($name);
        $dialogue = new Dialogue($name);
        
        $handle = fopen($path, 'r');
        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            $dialogue->setHistory([
                ...$dialogue->getHistory(),
                Message::fromArray($data),
            ]);
        }
        fclose($handle);
        
        return $dialogue;
    }

    private function getPath(string $name): string
    {
        return $this->directory . '/' . $name . $this->extension;
    }
}
```

#### File Format Example

```jsonl
{"role":"system","content":"You are a helpful assistant."}
{"role":"user","content":"What is 2+2?"}
{"role":"assistant","content":"4"}
{"role":"user","content":"And 3+3?"}
{"role":"assistant","content":"6"}
{"role":"tool","content":"{\"result\":42}","tool_call_id":"call_abc","name":"calculator"}
```

#### Considerations

- **Atomic writes**: Use `LOCK_EX` for concurrent access safety
- **Rotation**: Consider max file size / message count rotation
- **Compression**: Optional gzip for archival (`.jsonl.gz`)
- **Indexing**: Separate index file for quick lookup by timestamp/message ID
- **Message::fromArray()**: Need to add factory method to Message class

#### Integration with Dialogue

```php
// Option 1: Persistence as separate concern
$persistence = new JsonlDialoguePersistence('/var/dialogues');
$dialogue = $persistence->load('user-123-session-456');
$dialogue->setUserMessage('Hello');
$persistence->save($dialogue);

// Option 2: Dialogue with auto-persistence (decorator pattern)
$dialogue = new PersistentDialogue(
    new Dialogue('session-123'),
    new JsonlDialoguePersistence('/var/dialogues'),
);
$dialogue->setUserMessage('Hello'); // Auto-saves
```

#### Future Extensions

- **SQLite driver**: For queryable history
- **Encryption**: At-rest encryption for sensitive conversations
