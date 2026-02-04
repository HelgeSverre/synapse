<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Prompt;

final class TextPrompt extends BasePrompt
{
    private string $content = '';

    public function addContent(string $content): self
    {
        $this->content .= ($this->content !== '' && $this->content !== '0' ? "\n\n" : '').$content;

        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /** @param array<string, mixed> $values */
    public function render(array $values = []): string
    {
        return $this->renderTemplate($this->content, $values);
    }

    public function getType(): PromptType
    {
        return PromptType::Text;
    }
}
