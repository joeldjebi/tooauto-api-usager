<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Services\Assistant\AssistantOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AssistantController extends Controller
{
    public function __construct(private AssistantOrchestrator $orchestrator)
    {
    }

    public function chat(Request $request)
    {
        if (!config('services.assistant.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Assistant temporairement indisponible.',
            ], 503);
        }

        $validated = $request->validate([
            'conversation_id' => 'nullable|integer',
            'message' => 'required|string|max:2000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $context = [
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ];

        $user = auth()->user();
        $guard = auth()->getDefaultDriver();

        // Un provider local (Ollama) peut être bien plus lent qu'une API cloud : on élargit
        // la limite d'exécution PHP en fonction du budget réel (itérations x timeout configuré).
        $perCallTimeout = max(
            (int) config('services.assistant.ollama.timeout', 120),
            30
        );
        set_time_limit(((int) config('services.assistant.max_tool_iterations', 5)) * $perCallTimeout + 10);

        try {
            $result = $this->orchestrator->chat(
                $user,
                $guard,
                $validated['conversation_id'] ?? null,
                $validated['message'],
                $context
            );
        } catch (RuntimeException $e) {
            Log::error('[assistant] requête chat rejetée', [
                'user_id' => $user->id,
                'guard' => $guard,
                'conversation_id' => $validated['conversation_id'] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réponse de l\'assistant.',
            'data' => [
                'conversation_id' => $result['conversation']->id,
                'message' => [
                    'role' => 'assistant',
                    'content' => $result['message'],
                ],
                'usage' => $result['usage'],
                'remaining_weekly_tokens' => $result['remaining_weekly_tokens'],
                'results' => $result['results'],
            ],
        ]);
    }

    public function conversations(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = auth()->user();
        $guard = auth()->getDefaultDriver();

        $conversations = AssistantConversation::where('user_id', $user->id)
            ->where('user_type', $guard)
            ->with('lastAssistantMessage')
            ->orderByDesc('updated_at')
            ->paginate($validated['per_page'] ?? 15);

        $conversations->getCollection()->transform(function (AssistantConversation $conversation) {
            return [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message' => $conversation->lastAssistantMessage->content ?? null,
                'created_at' => optional($conversation->created_at)->toDateTimeString(),
                'updated_at' => optional($conversation->updated_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des conversations avec l\'assistant.',
            'conversations' => $conversations->items(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
                'last_page' => $conversations->lastPage(),
                'from' => $conversations->firstItem(),
                'to' => $conversations->lastItem(),
                'has_more_pages' => $conversations->hasMorePages(),
                'next_page_url' => $conversations->nextPageUrl(),
                'prev_page_url' => $conversations->previousPageUrl(),
            ],
        ]);
    }

    public function messages(Request $request, int $conversation)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = auth()->user();
        $guard = auth()->getDefaultDriver();

        $conversationModel = AssistantConversation::where('id', $conversation)
            ->where('user_id', $user->id)
            ->where('user_type', $guard)
            ->first();

        if (!$conversationModel) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation introuvable.',
            ], 404);
        }

        $messages = $conversationModel->messages()
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 30);

        return response()->json([
            'success' => true,
            'message' => 'Historique de la conversation.',
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
                'has_more_pages' => $messages->hasMorePages(),
                'next_page_url' => $messages->nextPageUrl(),
                'prev_page_url' => $messages->previousPageUrl(),
            ],
        ]);
    }
}
