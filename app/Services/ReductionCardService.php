<?php

namespace App\Services;

use App\Models\AbonnementUsager;
use App\Models\Etablissement;
use App\Models\ReductionCard;
use App\Models\ReductionCardHistory;
use App\Models\Station_service;
use App\Models\StationDeLavage;
use App\Models\UserReductionCard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReductionCardService
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    public function assignCardsToSubscription(AbonnementUsager $abonnement): int
    {
        $cards = ReductionCard::where('forfait_usager_id', $abonnement->forfait_id)
            ->where('statut', 1)
            ->get();

        $createdCount = 0;

        foreach ($cards as $card) {
            $existing = UserReductionCard::where('reduction_card_id', $card->id)
                ->where('abonnement_usager_id', $abonnement->id)
                ->first();

            if ($existing) {
                continue;
            }

            UserReductionCard::create([
                'reduction_card_id' => $card->id,
                'user_id' => $abonnement->user_id,
                'abonnement_usager_id' => $abonnement->id,
                'forfait_usager_id' => $abonnement->forfait_id,
                'card_code' => $this->generateCardCode(),
                'qr_code' => $this->generateQrCode(),
                'date_debut' => $abonnement->date_debut,
                'date_fin' => $abonnement->date_fin,
                'statut' => 1,
            ]);

            $createdCount++;
        }

        return $createdCount;
    }

    public function listActiveUserCards(int $userId)
    {
        return UserReductionCard::with(['reductionCard.forfaitUsager', 'forfaitUsager'])
            ->where('user_id', $userId)
            ->where('statut', 1)
            ->whereDate('date_fin', '>=', Carbon::today())
            ->whereHas('reductionCard', function ($query) {
                $query->where('statut', 1);
            })
            ->orderBy('date_fin')
            ->get()
            ->map(function (UserReductionCard $userCard) {
                return $this->formatUserCard($userCard);
            })
            ->values();
    }

    public function listUserLoyaltyCards(int $userId, array $filters)
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $cards = UserReductionCard::with(['reductionCard.forfaitUsager', 'forfaitUsager'])
            ->where('user_id', $userId)
            ->when(isset($filters['statut']), function ($query) use ($filters) {
                $query->where('statut', (int) $filters['statut']);
            })
            ->when(!empty($filters['card_code']), function ($query) use ($filters) {
                $query->where('card_code', 'like', '%' . trim($filters['card_code']) . '%');
            })
            ->when(!empty($filters['valid_only']), function ($query) {
                $query->where('statut', 1)
                    ->where(function ($query) {
                        $query->whereNull('date_fin')
                            ->orWhereDate('date_fin', '>=', Carbon::today());
                    })
                    ->whereHas('reductionCard', function ($query) {
                        $query->where('statut', 1);
                    });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($filters);

        $cards->getCollection()->transform(function (UserReductionCard $userCard) {
            return $this->formatUserCard($userCard);
        });

        return $cards;
    }

    public function verifyUserCard(int $userId, ?string $cardCode = null, ?string $qrCode = null): array
    {
        $userCard = $this->getValidUserCard($userId, $cardCode, $qrCode);

        return [
            'carte' => $this->formatUserCard($userCard),
            'reduction_applicable' => [
                'discount_type' => $this->discountType($userCard->reductionCard),
                'discount_value' => $this->discountValue($userCard->reductionCard),
            ],
        ];
    }

    public function listUserReductionHistory(int $userId, array $filters)
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $histories = ReductionCardHistory::with([
                'userReductionCard',
                'reductionCard',
                'forfaitUsager',
                'abonnementUsager',
            ])
            ->where('user_id', $userId)
            ->when(!empty($filters['establishment_type']), function ($query) use ($filters) {
                $query->where('establishment_type', $filters['establishment_type']);
            })
            ->when(!empty($filters['date_debut']), function ($query) use ($filters) {
                $query->whereDate('used_at', '>=', $filters['date_debut']);
            })
            ->when(!empty($filters['date_fin']), function ($query) use ($filters) {
                $query->whereDate('used_at', '<=', $filters['date_fin']);
            })
            ->when(!empty($filters['card_code']), function ($query) use ($filters) {
                $query->whereHas('userReductionCard', function ($query) use ($filters) {
                    $query->where('card_code', trim($filters['card_code']));
                });
            })
            ->orderByDesc('used_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($filters);

        $establishments = $this->loadHistoryEstablishments($histories->getCollection());

        $histories->getCollection()->transform(function (ReductionCardHistory $history) use ($establishments) {
            return $this->formatReductionHistory($history, $establishments);
        });

        return $histories;
    }

    public function applyDiscount(int $userId, array $data): array
    {
        $userCard = $this->getValidUserCard(
            $userId,
            $data['card_code'] ?? null,
            $data['qr_code'] ?? null
        );

        $card = $userCard->reductionCard;
        $discountType = $this->discountType($card);
        $discountValue = $this->discountValue($card);
        $montantInitial = round((float) $data['montant_initial'], 2);

        if ($discountType === 'percentage') {
            $montantReduction = round(($montantInitial * $discountValue) / 100, 2);
        } elseif ($discountType === 'fixed') {
            $montantReduction = round($discountValue, 2);
        } else {
            throw new RuntimeException('Type de réduction invalide.');
        }

        $montantReduction = min($montantReduction, $montantInitial);
        $montantFinal = round($montantInitial - $montantReduction, 2);

        $history = DB::transaction(function () use ($userCard, $card, $discountType, $discountValue, $montantInitial, $montantReduction, $montantFinal, $data) {
            return ReductionCardHistory::create([
                'user_reduction_card_id' => $userCard->id,
                'reduction_card_id' => $card->id,
                'user_id' => $userCard->user_id,
                'abonnement_usager_id' => $userCard->abonnement_usager_id,
                'forfait_usager_id' => $userCard->forfait_usager_id,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'montant_initial' => $montantInitial,
                'montant_reduction' => $montantReduction,
                'montant_final' => $montantFinal,
                'applied_by_id' => $data['applied_by_id'],
                'establishment_type' => $data['establishment_type'],
                'establishment_id' => $data['establishment_id'],
                'notes' => $data['notes'] ?? null,
                'used_at' => now(),
            ]);
        });

        return [
            'history' => $history,
            'carte' => $this->formatUserCard($userCard),
            'montant_initial' => $montantInitial,
            'montant_reduction' => $montantReduction,
            'montant_final' => $montantFinal,
        ];
    }

    private function getValidUserCard(int $userId, ?string $cardCode = null, ?string $qrCode = null): UserReductionCard
    {
        $cardCode = $cardCode ? trim($cardCode) : null;
        $qrCode = $qrCode ? trim($qrCode) : null;

        if (!$cardCode && !$qrCode) {
            throw new RuntimeException('Le code carte ou le QR code est requis.');
        }

        $userCard = UserReductionCard::with(['reductionCard.forfaitUsager', 'forfaitUsager'])
            ->where('user_id', $userId)
            ->where(function ($query) use ($cardCode, $qrCode) {
                if ($cardCode) {
                    $query->orWhere('card_code', $cardCode);
                }

                if ($qrCode) {
                    $query->orWhere('qr_code', $qrCode);
                }
            })
            ->first();

        if (!$userCard) {
            throw new RuntimeException('Carte de réduction introuvable pour cet usager.');
        }

        if ((int) $userCard->statut !== 1) {
            throw new RuntimeException('Carte de réduction inactive.');
        }

        if (!$userCard->reductionCard || (int) $userCard->reductionCard->statut !== 1) {
            throw new RuntimeException('Carte de réduction désactivée.');
        }

        if ($userCard->date_fin && Carbon::parse($userCard->date_fin)->lt(Carbon::today())) {
            throw new RuntimeException('Carte de réduction expirée.');
        }

        return $userCard;
    }

    private function generateCardCode(): string
    {
        do {
            $value = 'RC-' . now()->format('dm') . '-' . Str::upper(Str::random(8));
        } while (UserReductionCard::where('card_code', $value)->exists());

        return $value;
    }

    private function generateQrCode(): string
    {
        do {
            $value = 'TOOAUTO-REDUCTION-' . Str::upper(Str::random(18));
        } while (UserReductionCard::where('qr_code', $value)->exists());

        return $value;
    }

    private function formatUserCard(UserReductionCard $userCard): array
    {
        $card = $userCard->reductionCard;
        $forfait = $userCard->forfaitUsager ?: optional($card)->forfaitUsager;

        return [
            'id' => $userCard->id,
            'reduction_card_id' => $userCard->reduction_card_id,
            'abonnement_usager_id' => $userCard->abonnement_usager_id,
            'forfait_usager_id' => $userCard->forfait_usager_id,
            'nom' => $card->nom ?? $card->name ?? $card->libelle ?? null,
            'discount_type' => $this->discountType($card),
            'discount_value' => $this->discountValue($card),
            'description' => $card->description ?? null,
            'card_code' => $userCard->card_code,
            'qr_code' => $userCard->qr_code,
            'date_debut' => optional($userCard->date_debut)->toDateString(),
            'date_fin' => optional($userCard->date_fin)->toDateString(),
            'statut' => (int) $userCard->statut,
            'est_expiree' => $userCard->date_fin
                ? Carbon::parse($userCard->date_fin)->lt(Carbon::today())
                : false,
            'forfait' => $forfait ? [
                'id' => $forfait->id,
                'libelle' => $forfait->libelle ?? $forfait->nom ?? null,
                'prix' => $forfait->prix ?? null,
                'duree' => $forfait->duree ?? null,
            ] : null,
            'created_at' => optional($userCard->created_at)->toDateTimeString(),
        ];
    }

    private function loadHistoryEstablishments($histories): array
    {
        $idsByType = $histories
            ->groupBy('establishment_type')
            ->map(function ($items) {
                return $items->pluck('establishment_id')->filter()->unique()->values()->all();
            });

        return [
            'etablissement' => Etablissement::whereIn('id', $idsByType->get('etablissement', []))
                ->get()
                ->keyBy('id'),
            'lavage' => StationDeLavage::whereIn('id', $idsByType->get('lavage', []))
                ->get()
                ->keyBy('id'),
            'station' => Station_service::whereIn('id', $idsByType->get('station', []))
                ->get()
                ->keyBy('id'),
        ];
    }

    private function formatReductionHistory(ReductionCardHistory $history, array $establishments): array
    {
        $userCard = $history->userReductionCard;
        $card = $history->reductionCard;
        $forfait = $history->forfaitUsager;
        $establishment = $establishments[$history->establishment_type]->get($history->establishment_id);

        return [
            'id' => $history->id,
            'discount_type' => $history->discount_type,
            'discount_value' => (float) $history->discount_value,
            'montant_initial' => (float) $history->montant_initial,
            'montant_reduction' => (float) $history->montant_reduction,
            'montant_final' => (float) $history->montant_final,
            'notes' => $history->notes,
            'used_at' => optional($history->used_at)->toDateTimeString(),
            'carte' => [
                'id' => $userCard->id ?? null,
                'reduction_card_id' => $history->reduction_card_id,
                'nom' => $card->name ?? $card->nom ?? null,
                'card_code' => $userCard->card_code ?? null,
                'qr_code' => $userCard->qr_code ?? null,
            ],
            'forfait' => $forfait ? [
                'id' => $forfait->id,
                'libelle' => $forfait->libelle ?? null,
            ] : null,
            'abonnement_usager_id' => $history->abonnement_usager_id,
            'establishment_type' => $history->establishment_type,
            'establishment_id' => $history->establishment_id,
            'establishment' => $this->formatHistoryEstablishment($establishment),
        ];
    }

    private function formatHistoryEstablishment($establishment): ?array
    {
        if (!$establishment) {
            return null;
        }

        $logo = $establishment->logo ?? null;

        return [
            'id' => $establishment->id,
            'name' => $establishment->name ?? $establishment->nom ?? null,
            'adresse' => $establishment->adresse ?? $establishment->adresse_map ?? null,
            'contact' => $establishment->contact ?? $establishment->mobile ?? $establishment->telephone ?? null,
            'logo' => $logo,
            'logo_url' => $this->signedWasabiUrl($logo),
        ];
    }

    private function signedWasabiUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        try {
            return $this->wasabiService->temporaryUrl($path);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function discountType(?ReductionCard $card): ?string
    {
        if (!$card) {
            return null;
        }

        return $card->discount_type
            ?? $card->type_reduction
            ?? $card->reduction_type
            ?? $card->type
            ?? null;
    }

    private function discountValue(?ReductionCard $card): float
    {
        if (!$card) {
            return 0;
        }

        return (float) (
            $card->discount_value
            ?? $card->valeur_reduction
            ?? $card->reduction_value
            ?? $card->value
            ?? 0
        );
    }
}
