<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MistralClient implements LlmClient
{
    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';

    private ?string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.assistant.mistral.api_key');
        $this->model = config('services.assistant.mistral.model');
    }

    public function send(array $messages, array $tools = [], ?string $system = null): array
    {
        if (!$this->apiKey) {
            throw new RuntimeException("Clé API Mistral manquante (MISTRAL_API_KEY).");
        }

        $wireMessages = $this->toWireMessages($messages);

        if ($system) {
            array_unshift($wireMessages, ['role' => 'system', 'content' => $system]);
        }

        $payload = array_filter([
            'model' => $this->model,
            'messages' => $wireMessages,
            'tools' => $this->toWireTools($tools) ?: null,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post(self::API_URL, $payload);

            $response->throw();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Appel à l\'API Mistral échoué : ' . $e->getMessage(),
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
                        'content' => null,
                        'tool_calls' => [[
                            'id' => $message['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => $message['name'],
                                'arguments' => json_encode($message['input']),
                            ],
                        ]],
                    ];
                    break;

                case 'tool_result':
                    $wire[] = [
                        'role' => 'tool',
                        'tool_call_id' => $message['id'],
                        'name' => $message['name'],
                        'content' => json_encode($message['output']),
                    ];
                    break;
            }
        }

        return $wire;
    }

    private function fromWireResponse(array $body): array
    {
        $message = $body['choices'][0]['message'] ?? [];
        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $call) {
            $function = $call['function'] ?? [];
            $arguments = $function['arguments'] ?? [];

            if (is_string($arguments)) {
                $arguments = json_decode($arguments, true) ?? [];
            }

            $toolCalls[] = [
                'id' => $call['id'] ?? '',
                'name' => $function['name'] ?? '',
                'input' => $arguments,
            ];
        }

        return [
            'text' => trim($message['content'] ?? ''),
            'tool_calls' => $toolCalls,
            'usage' => [
                'input_tokens' => $body['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $body['usage']['completion_tokens'] ?? 0,
            ],
        ];
    }
}
