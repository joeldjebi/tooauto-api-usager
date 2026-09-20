<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AnthropicClient implements LlmClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    private ?string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.assistant.api_key');
        $this->model = config('services.assistant.model');
    }

    public function send(array $messages, array $tools = [], ?string $system = null): array
    {
        if (!$this->apiKey) {
            throw new RuntimeException("Clé API Anthropic manquante (ANTHROPIC_API_KEY).");
        }

        $payload = array_filter([
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => $this->toWireMessages($messages),
            'tools' => $this->toWireTools($tools) ?: null,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])->timeout(30)->post(self::API_URL, $payload);

            $response->throw();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Appel à l\'API Anthropic échoué : ' . $e->getMessage(),
                previous: $e
            );
        }

        return $this->fromWireResponse($response->json());
    }

    private function toWireTools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['input_schema'],
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
                        'content' => [[
                            'type' => 'tool_use',
                            'id' => $message['id'],
                            'name' => $message['name'],
                            'input' => $message['input'],
                        ]],
                    ];
                    break;

                case 'tool_result':
                    $wire[] = [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'tool_result',
                            'tool_use_id' => $message['id'],
                            'content' => json_encode($message['output']),
                        ]],
                    ];
                    break;
            }
        }

        return $wire;
    }

    private function fromWireResponse(array $body): array
    {
        $content = $body['content'] ?? [];
        $text = '';
        $toolCalls = [];

        foreach ($content as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }

            if (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return [
            'text' => trim($text),
            'tool_calls' => $toolCalls,
            'usage' => [
                'input_tokens' => $body['usage']['input_tokens'] ?? 0,
                'output_tokens' => $body['usage']['output_tokens'] ?? 0,
            ],
        ];
    }
}
