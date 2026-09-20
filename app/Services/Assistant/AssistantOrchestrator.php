<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\Contracts\LlmClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AssistantOrchestrator
{
    public function __construct(
        private LlmClient $client,
        private ToolRegistry $tools,
        private AssistantQuotaService $quota,
    ) {
    }

    public function chat($authenticatable, string $guard, ?int $conversationId, string $userMessage, array $context = []): array
    {
        $startedAt = microtime(true);

        Log::info('[assistant] requête reçue', [
            'user_id' => $authenticatable->id,
            'guard' => $guard,
            'conversation_id' => $conversationId,
            'provider' => get_class($this->client),
            'message' => Str::limit($userMessage, 300),
            'has_location' => isset($context['latitude'], $context['longitude']),
        ]);

        $this->quota->assertNotExceeded($authenticatable, $guard);

        $conversation = $this->resolveConversation($authenticatable, $guard, $conversationId, $userMessage);

        Log::info('[assistant] conversation résolue', [
            'conversation_id' => $conversation->id,
            'is_new' => $conversation->wasRecentlyCreated,
        ]);

        AssistantMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $messages = $this->buildHistory($conversation);

        $maxIterations = (int) config('services.assistant.max_tool_iterations', 5);
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $finalText = '';
        $toolResults = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $iteration = $i + 1;

            Log::debug('[assistant] appel LLM', [
                'conversation_id' => $conversation->id,
                'iteration' => $iteration,
                'messages_count' => count($messages),
                'tools' => array_column($this->tools->definitions(), 'name'),
            ]);

            $callStartedAt = microtime(true);
            $response = $this->client->send($messages, $this->tools->definitions(), $this->systemPrompt());
            $callDurationMs = (int) round((microtime(true) - $callStartedAt) * 1000);

            $totalInputTokens += $response['usage']['input_tokens'];
            $totalOutputTokens += $response['usage']['output_tokens'];

            Log::info('[assistant] réponse LLM reçue', [
                'conversation_id' => $conversation->id,
                'iteration' => $iteration,
                'duration_ms' => $callDurationMs,
                'input_tokens' => $response['usage']['input_tokens'],
                'output_tokens' => $response['usage']['output_tokens'],
                'tool_calls' => array_column($response['tool_calls'], 'name'),
                'text_preview' => $response['tool_calls'] ? null : Str::limit($response['text'], 300),
            ]);

            if (empty($response['tool_calls'])) {
                $finalText = $response['text'];
                break;
            }

            foreach ($response['tool_calls'] as $call) {
                Log::info('[assistant] exécution outil', [
                    'conversation_id' => $conversation->id,
                    'iteration' => $iteration,
                    'tool' => $call['name'],
                    'input' => $call['input'],
                ]);

                $toolStartedAt = microtime(true);
                $output = $this->executeTool($call['name'], $call['input'], $authenticatable, $guard, $context);
                $toolDurationMs = (int) round((microtime(true) - $toolStartedAt) * 1000);

                Log::info('[assistant] résultat outil', [
                    'conversation_id' => $conversation->id,
                    'iteration' => $iteration,
                    'tool' => $call['name'],
                    'duration_ms' => $toolDurationMs,
                    'has_error' => isset($output['error']),
                    'output_preview' => Str::limit(json_encode($output), 500),
                ]);

                AssistantMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'tool',
                    'tool_name' => $call['name'],
                    'tool_input' => ['id' => $call['id'], 'input' => $call['input']],
                    'tool_output' => $output,
                ]);

                if (!isset($output['error'])) {
                    $toolResults[] = ['tool' => $call['name'], 'data' => $output];
                }

                $messages[] = ['role' => 'tool_call', 'id' => $call['id'], 'name' => $call['name'], 'input' => $call['input']];
                $messages[] = ['role' => 'tool_result', 'id' => $call['id'], 'name' => $call['name'], 'output' => $output];
            }
        }

        if ($finalText === '') {
            $finalText = "Je n'ai pas pu obtenir de réponse complète, réessaie ta question.";

            Log::warning('[assistant] itérations maximum atteintes sans réponse finale', [
                'conversation_id' => $conversation->id,
                'max_iterations' => $maxIterations,
            ]);
        }

        AssistantMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $finalText,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ]);

        $remaining = $this->quota->remaining($authenticatable, $guard);

        Log::info('[assistant] réponse finale envoyée', [
            'conversation_id' => $conversation->id,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'total_input_tokens' => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
            'remaining_weekly_tokens' => $remaining,
            'response_preview' => Str::limit($finalText, 300),
        ]);

        return [
            'conversation' => $conversation,
            'message' => $finalText,
            'usage' => [
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
            ],
            'remaining_weekly_tokens' => $remaining,
            'results' => $toolResults,
        ];
    }

    private function resolveConversation($authenticatable, string $guard, ?int $conversationId, string $userMessage): AssistantConversation
    {
        if ($conversationId) {
            $conversation = AssistantConversation::where('id', $conversationId)
                ->where('user_id', $authenticatable->id)
                ->where('user_type', $guard)
                ->first();

            if (!$conversation) {
                throw new RuntimeException('Conversation introuvable.');
            }

            return $conversation;
        }

        return AssistantConversation::create([
            'user_id' => $authenticatable->id,
            'user_type' => $guard,
            'title' => Str::limit($userMessage, 60),
        ]);
    }

    private function buildHistory(AssistantConversation $conversation): array
    {
        $messages = [];

        foreach ($conversation->messages()->orderBy('id')->get() as $message) {
            if ($message->role === 'user') {
                $messages[] = ['role' => 'user', 'content' => $message->content];
                continue;
            }

            if ($message->role === 'assistant') {
                $messages[] = ['role' => 'assistant', 'content' => $message->content];
                continue;
            }

            if ($message->role === 'tool') {
                $toolUseId = $message->tool_input['id'] ?? ('toolu_' . $message->id);
                $toolInput = $message->tool_input['input'] ?? [];

                $messages[] = ['role' => 'tool_call', 'id' => $toolUseId, 'name' => $message->tool_name, 'input' => $toolInput];
                $messages[] = ['role' => 'tool_result', 'id' => $toolUseId, 'name' => $message->tool_name, 'output' => $message->tool_output];
            }
        }

        return $messages;
    }

    private function executeTool(string $name, array $input, $authenticatable, string $guard, array $context): array
    {
        if (!$this->tools->has($name)) {
            return ['error' => "Outil inconnu : {$name}"];
        }

        try {
            return $this->tools->get($name)->handle($input, $authenticatable, $guard, $context);
        } catch (Throwable $e) {
            Log::error('[assistant] échec exécution outil', [
                'tool' => $name,
                'input' => $input,
                'exception' => $e->getMessage(),
            ]);

            return ['error' => "Erreur lors de l'exécution de l'outil : " . $e->getMessage()];
        }
    }

    private function systemPrompt(): string
    {
        return "Tu es l'assistant TOOAUTO, une application d'assistance automobile. "
            . "Réponds en français, de façon concise et utile. "
            . "Utilise toujours les outils disponibles pour répondre aux questions sur les campagnes, "
            . "établissements, cartes de réduction ou stations de lavage : ne jamais inventer de données. "
            . "Si aucun outil ne correspond à la demande, dis-le clairement.";
    }
}
