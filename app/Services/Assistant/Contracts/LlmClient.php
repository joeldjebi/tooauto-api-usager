<?php

namespace App\Services\Assistant\Contracts;

interface LlmClient
{
    /**
     * @param array $messages Historique neutre :
     *   ['role' => 'user'|'assistant', 'content' => string]
     *   ['role' => 'tool_call', 'id' => string, 'name' => string, 'input' => array]
     *   ['role' => 'tool_result', 'id' => string, 'name' => string, 'output' => array]
     * @param array $tools Définitions neutres : [['name','description','input_schema'], ...]
     * @param string|null $system Prompt système.
     * @return array{text: string, tool_calls: array, usage: array{input_tokens:int, output_tokens:int}}
     */
    public function send(array $messages, array $tools = [], ?string $system = null): array;
}
