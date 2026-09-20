<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OllamaClient implements LlmClient
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.assistant.ollama.base_url'), '/');
        $this->model = config('services.assistant.ollama.model');
        $this->timeout = (int) config('services.assistant.ollama.timeout', 120);
    }

    public function send(array $messages, array $tools = [], ?string $system = null): array
    {
        $wireMessages = $this->toWireMessages($messages);

        if ($system) {
            array_unshift($wireMessages, ['role' => 'system', 'content' => $system]);
        }

        $payload = array_filter([
            'model' => $this->model,
            'messages' => $wireMessages,
            'tools' => $this->toWireTools($tools) ?: null,
            'stream' => false,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/api/chat', $payload);

            $response->throw();
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Appel à Ollama échoué ({$this->baseUrl}) : " . $e->getMessage(),
                previous: $e
            );
        }

        return $this->fromWireResponse($response->json());
    }

    private function toWireTools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['input_schema'],
            ],
        ], $tools);
    }

    private function toWireMessages(array $messages): array
    {
        $wire = [];

        foreach ($messages as $message) {
            switch ($message['role']) {
                case 'user':
                case 'assistant':
                    $wire[] = ['role' => $message['role'], 'content' => $message['content']];
                    break;

                case 'tool_call':
                    $wire[] = [
                        'role' => 'assistant',
                        'content' => '',
                        'tool_calls' => [[
                            'function' => [
                                'name' => $message['name'],
                                'arguments' => $message['input'],
                            ],
                        ]],
                    ];
                    break;

                case 'tool_result':
                    $wire[] = [
                        'role' => 'tool',
                        'content' => json_encode($message['output']),
                    ];
                    break;
            }
        }

        return $wire;
    }

    private function fromWireResponse(array $body): array
    {
        $message = $body['message'] ?? [];
        $toolCalls = $this->extractToolCalls($message);

        return [
            'text' => $toolCalls ? '' : trim($message['content'] ?? ''),
            'tool_calls' => $toolCalls,
            'usage' => [
                'input_tokens' => $body['prompt_eval_count'] ?? 0,
                'output_tokens' => $body['eval_count'] ?? 0,
            ],
        ];
    }

    private function extractToolCalls(array $message): array
    {
        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $call) {
            $function = $call['function'] ?? [];
            $arguments = $function['arguments'] ?? [];

            if (is_string($arguments)) {
                $arguments = json_decode($arguments, true) ?? [];
            }

            $toolCalls[] = [
                'id' => (string) Str::uuid(),
                'name' => $function['name'] ?? '',
                'input' => $arguments,
            ];
        }

        if (!empty($toolCalls)) {
            return $toolCalls;
        }

        // Repli : certains modèles locaux renvoient l'appel d'outil en JSON brut dans
        // "content" au lieu d'utiliser le champ natif "tool_calls" d'Ollama.
        $decoded = json_decode(trim($message['content'] ?? ''), true);

        if (is_array($decoded) && is_string($decoded['name'] ?? null) && is_array($decoded['arguments'] ?? null)) {
            return [[
                'id' => (string) Str::uuid(),
                'name' => $decoded['name'],
                'input' => $decoded['arguments'],
            ]];
        }

        return [];
    }
}
