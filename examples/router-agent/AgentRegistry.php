<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RouterAgent;

/**
 * Registry of available specialist agents.
 */
final class AgentRegistry
{
    /** @var array<string, AgentDefinition> */
    private array $agents = [];

    public function register(AgentDefinition $agent): void
    {
        $this->agents[$agent->name] = $agent;
    }

    public function get(string $name): ?AgentDefinition
    {
        return $this->agents[$name] ?? null;
    }

    /** @return list<AgentDefinition> */
    public function all(): array
    {
        return array_values($this->agents);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->agents);
    }

    public function getDescriptionForManager(): string
    {
        $lines = ['Available specialist agents:'];

        foreach ($this->agents as $agent) {
            $lines[] = "- **{$agent->name}**: {$agent->toToolDescription()}";
        }

        return implode("\n", $lines);
    }

    /**
     * Create a registry with default specialist agents.
     */
    public static function withDefaults(): self
    {
        $registry = new self;

        // Code Review Agent
        $registry->register(new AgentDefinition(
            name: 'code_reviewer',
            description: 'Reviews code for quality, best practices, and potential bugs',
            systemPrompt: <<<'PROMPT'
            You are an expert code reviewer. Analyze the provided code for:
            - Code quality and readability
            - Best practices and design patterns
            - Potential bugs or edge cases
            - Performance considerations
            - Suggestions for improvement

            Be constructive and specific in your feedback. Reference line numbers when possible.
            PROMPT,
            capabilities: ['code analysis', 'best practices', 'bug detection', 'refactoring suggestions'],
        ));

        // Security Agent
        $registry->register(new AgentDefinition(
            name: 'security_auditor',
            description: 'Audits code and systems for security vulnerabilities',
            systemPrompt: <<<'PROMPT'
            You are a security expert. Analyze the provided content for:
            - Common vulnerabilities (OWASP Top 10)
            - Injection risks (SQL, XSS, command injection)
            - Authentication and authorization issues
            - Data exposure risks
            - Insecure configurations

            Rate each finding by severity (Critical, High, Medium, Low, Info).
            Provide specific remediation steps.
            PROMPT,
            capabilities: ['vulnerability scanning', 'OWASP analysis', 'security recommendations'],
        ));

        // Research Agent
        $registry->register(new AgentDefinition(
            name: 'researcher',
            description: 'Researches topics and provides comprehensive summaries',
            systemPrompt: <<<'PROMPT'
            You are a research assistant. When given a topic:
            - Provide a comprehensive overview
            - Explain key concepts clearly
            - Include relevant examples
            - Cite sources when applicable
            - Organize information logically

            Be thorough but concise. Focus on accuracy.
            PROMPT,
            capabilities: ['topic research', 'summarization', 'explanation', 'examples'],
        ));

        // Documentation Agent
        $registry->register(new AgentDefinition(
            name: 'documenter',
            description: 'Creates or improves documentation for code and APIs',
            systemPrompt: <<<'PROMPT'
            You are a technical writer. When documenting:
            - Write clear, concise explanations
            - Include usage examples
            - Document parameters, return types, and exceptions
            - Follow standard documentation formats (JSDoc, PHPDoc, etc.)
            - Consider the target audience (developers)

            Good documentation is accurate, complete, and easy to understand.
            PROMPT,
            capabilities: ['API docs', 'code comments', 'README writing', 'tutorials'],
        ));

        return $registry;
    }
}
