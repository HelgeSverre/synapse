<?php

declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

use LlmExe\Executor\CallableExecutor;

final class RiskyTools
{
    /**
     * Safe tool - no approval needed
     */
    public static function readFile(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'read_file',
            description: 'Read contents of a file',
            handler: fn (array $args) => json_encode([
                'path' => $args['path'] ?? '',
                'content' => '[Mock file content for: '.($args['path'] ?? 'unknown').']',
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'File path to read'],
                ],
                'required' => ['path'],
            ],
            attributes: ['risk' => 'low'],
        );
    }

    /**
     * Medium risk - file modification
     */
    public static function writeFile(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'write_file',
            description: 'Write content to a file (overwrites existing)',
            handler: fn (array $args) => json_encode([
                'success' => true,
                'path' => $args['path'] ?? '',
                'bytes_written' => strlen($args['content'] ?? ''),
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'File path to write'],
                    'content' => ['type' => 'string', 'description' => 'Content to write'],
                ],
                'required' => ['path', 'content'],
            ],
            attributes: ['risk' => 'medium', 'description' => 'Overwrites file contents'],
        );
    }

    /**
     * High risk - file deletion
     */
    public static function deleteFiles(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'delete_files',
            description: 'Delete files matching a pattern',
            handler: fn (array $args) => json_encode([
                'success' => true,
                'pattern' => $args['pattern'] ?? '',
                'files_deleted' => rand(1, 50), // Mock
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'pattern' => ['type' => 'string', 'description' => 'Glob pattern for files to delete'],
                ],
                'required' => ['pattern'],
            ],
            attributes: ['risk' => 'high', 'description' => 'Permanently deletes files'],
        );
    }

    /**
     * Critical risk - execute shell command
     */
    public static function executeCommand(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'execute_command',
            description: 'Execute a shell command',
            handler: fn (array $args) => json_encode([
                'command' => $args['command'] ?? '',
                'output' => '[Mock output for: '.($args['command'] ?? '').']',
                'exit_code' => 0,
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'Shell command to execute'],
                ],
                'required' => ['command'],
            ],
            attributes: ['risk' => 'critical', 'description' => 'Executes arbitrary shell commands'],
        );
    }

    /**
     * Medium risk - send email
     */
    public static function sendEmail(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'send_email',
            description: 'Send an email',
            handler: fn (array $args) => json_encode([
                'success' => true,
                'to' => $args['to'] ?? '',
                'subject' => $args['subject'] ?? '',
                'message_id' => uniqid('msg_'),
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'to' => ['type' => 'string', 'description' => 'Recipient email'],
                    'subject' => ['type' => 'string', 'description' => 'Email subject'],
                    'body' => ['type' => 'string', 'description' => 'Email body'],
                ],
                'required' => ['to', 'subject', 'body'],
            ],
            attributes: ['risk' => 'medium', 'description' => 'Sends email to external recipient'],
        );
    }
}
