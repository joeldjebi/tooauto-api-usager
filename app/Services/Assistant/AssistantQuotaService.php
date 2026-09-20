<?php

namespace App\Services\Assistant;

use App\Models\AbonnementUsager;
use App\Models\AssistantMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AssistantQuotaService
{
    public function limitFor($authenticatable, string $guard): int
    {
        if ($guard === 'api' && $authenticatable instanceof User) {
            $abonnement = AbonnementUsager::where('user_id', $authenticatable->id)
                ->where('statut', 1)
                ->with('forfait')
                ->latest()
                ->first();

            $override = $abonnement?->forfait?->weekly_token_limit;

            if ($override !== null) {
                return (int) $override;
            }
        }

        return (int) config('services.assistant.weekly_token_limit');
    }

    public function used($authenticatable, string $guard): int
    {
        return (int) AssistantMessage::query()
            ->join('assistant_conversations', 'assistant_conversations.id', '=', 'assistant_messages.conversation_id')
            ->where('assistant_conversations.user_id', $authenticatable->id)
            ->where('assistant_conversations.user_type', $guard)
            ->where('assistant_messages.role', 'assistant')
            ->where('assistant_messages.created_at', '>=', Carbon::now()->subDays(7))
            ->sum(DB::raw('assistant_messages.input_tokens + assistant_messages.output_tokens'));
    }

    public function remaining($authenticatable, string $guard): int
    {
        return max(0, $this->limitFor($authenticatable, $guard) - $this->used($authenticatable, $guard));
    }

    public function assertNotExceeded($authenticatable, string $guard): void
    {
        if ($this->remaining($authenticatable, $guard) <= 0) {
            Log::warning('[assistant] quota hebdomadaire dépassé', [
                'user_id' => $authenticatable->id,
                'guard' => $guard,
                'limit' => $this->limitFor($authenticatable, $guard),
                'used' => $this->used($authenticatable, $guard),
            ]);

            throw new RuntimeException(
                "Limite hebdomadaire de tokens de l'assistant atteinte. Réessayez plus tard."
            );
        }
    }
}
