<?php

namespace App\Services\Assistant\Tools;

use App\Services\Assistant\Contracts\AssistantTool;
use App\Services\CampaignSearchService;

class SearchCampaignsTool implements AssistantTool
{
    public function __construct(private CampaignSearchService $campaignSearchService)
    {
    }

    public function name(): string
    {
        return 'search_campaigns';
    }

    public function description(): string
    {
        return "Recherche des campagnes de réduction actuellement disponibles (lavage, station-service, "
            . "établissement) selon un texte libre et des filtres optionnels. Utilise cet outil dès qu'un "
            . "usager cherche une promotion, une réduction ou une offre.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => "Texte libre à rechercher dans le nom, la description ou les conditions de la campagne.",
                ],
                'establishment_type' => [
                    'type' => 'string',
                    'enum' => ['lavage', 'station', 'etablissement'],
                    'description' => "Type d'établissement concerné.",
                ],
                'discount_type' => [
                    'type' => 'string',
                    'enum' => ['percentage', 'fixed'],
                ],
                'max_price' => [
                    'type' => 'number',
                    'description' => 'Prix promotionnel maximum souhaité.',
                ],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input, $authenticatable, string $guard, array $context = []): array
    {
        $filters = [
            'available_only' => true,
            'per_page' => (int) config('services.assistant.max_results', 10),
            'search' => $input['search'] ?? null,
            'establishment_type' => $input['establishment_type'] ?? null,
            'discount_type' => $input['discount_type'] ?? null,
            'max_price' => $input['max_price'] ?? null,
        ];

        $campaigns = $this->campaignSearchService->search(array_filter($filters, fn ($value) => $value !== null));

        return [
            'total' => $campaigns->total(),
            'campaigns' => collect($campaigns->items())->map(function (array $campaign) {
                return [
                    'id' => $campaign['id'],
                    'name' => $campaign['name'],
                    'establishment_type' => $campaign['establishment_type'],
                    'establishment_name' => $campaign['establishment']['name'] ?? null,
                    'discount_type' => $campaign['discount_type'],
                    'discount_value' => $campaign['discount_value'],
                    'promotional_price' => $campaign['promotional_price'],
                    'montant_reduction' => $campaign['montant_reduction'],
                    'date_fin' => $campaign['date_fin'],
                    'quantity_remaining' => $campaign['quantity_remaining'],
                ];
            })->values()->all(),
        ];
    }
}
