<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\AgenticTools;

use HelgeSverre\Synapse\Executor\CallableExecutor;

final class NotesTool
{
    /** @var array<string, array{id: string, content: string, created_at: string}> */
    private static array $notes = [];

    private static int $nextId = 1;

    public static function reset(): void
    {
        self::$notes = [];
        self::$nextId = 1;
    }

    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'notes',
            description: 'Manage notes: add new notes, list all notes, get a specific note, or delete a note.',
            handler: function (array $args): string {
                $action = $args['action'] ?? 'list';

                return match ($action) {
                    'add' => self::addNote($args['content'] ?? ''),
                    'list' => self::listNotes(),
                    'get' => self::getNote($args['id'] ?? ''),
                    'delete' => self::deleteNote($args['id'] ?? ''),
                    default => json_encode(['error' => "Unknown action: {$action}"], JSON_THROW_ON_ERROR),
                };
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['add', 'list', 'get', 'delete'],
                        'description' => 'The action to perform on notes',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content for the note (required for add action)',
                    ],
                    'id' => [
                        'type' => 'string',
                        'description' => 'Note ID (required for get and delete actions)',
                    ],
                ],
                'required' => ['action'],
            ],
        );
    }

    /**
     * @return array{id: string, content: string, created_at: string}
     */
    private static function createNote(string $id, string $content, string $createdAt): array
    {
        return [
            'id' => $id,
            'content' => $content,
            'created_at' => $createdAt,
        ];
    }

    private static function addNote(string $content): string
    {
        if ($content === '') {
            return json_encode(['error' => 'Content is required for adding a note'], JSON_THROW_ON_ERROR);
        }

        $id = (string) self::$nextId++;
        $createdAt = date('Y-m-d H:i:s');
        $note = self::createNote($id, $content, $createdAt);

        /** @phpstan-ignore assign.propertyType */
        self::$notes[$id] = $note;

        return json_encode([
            'success' => true,
            'message' => 'Note added successfully',
            'note' => $note,
        ], JSON_THROW_ON_ERROR);
    }

    private static function listNotes(): string
    {
        return json_encode([
            'success' => true,
            'count' => count(self::$notes),
            'notes' => array_values(self::$notes),
        ], JSON_THROW_ON_ERROR);
    }

    private static function getNote(string $id): string
    {
        if ($id === '') {
            return json_encode(['error' => 'Note ID is required'], JSON_THROW_ON_ERROR);
        }

        if (! isset(self::$notes[$id])) {
            return json_encode(['error' => "Note with ID {$id} not found"], JSON_THROW_ON_ERROR);
        }

        return json_encode([
            'success' => true,
            'note' => self::$notes[$id],
        ], JSON_THROW_ON_ERROR);
    }

    private static function deleteNote(string $id): string
    {
        if ($id === '') {
            return json_encode(['error' => 'Note ID is required'], JSON_THROW_ON_ERROR);
        }

        if (! isset(self::$notes[$id])) {
            return json_encode(['error' => "Note with ID {$id} not found"], JSON_THROW_ON_ERROR);
        }

        $note = self::$notes[$id];
        unset(self::$notes[$id]);

        return json_encode([
            'success' => true,
            'message' => 'Note deleted successfully',
            'deleted_note' => $note,
        ], JSON_THROW_ON_ERROR);
    }
}
