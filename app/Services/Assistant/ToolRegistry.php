<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Contracts\AssistantTool;
use RuntimeException;

class ToolRegistry
{
    /** @var array<string, AssistantTool> */
    private array $tools = [];

    public function register(AssistantTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): AssistantTool
    {
        if (!isset($this->tools[$name])) {
            throw new RuntimeException("Outil assistant inconnu : {$name}");
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Définitions neutres des outils (indépendantes du fournisseur LLM).
     */
    public function definitions(): array
    {
        return array_values(array_map(function (AssistantTool $tool) {
            return [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ];
        }, $this->tools));
    }
}
