<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReductionCampaign;
use App\Services\CampaignSearchService;
use Illuminate\Http\Request;

class UserCampaignController extends Controller
{
    protected CampaignSearchService $campaignSearchService;

    public function __construct(CampaignSearchService $campaignSearchService)
    {
        $this->campaignSearchService = $campaignSearchService;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'establishment_type' => 'nullable|string|in:lavage,station,etablissement',
            'establishment_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'available_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $validated['available_only'] = $request->boolean('available_only', false);

        $campaigns = $this->campaignSearchService->search($validated);
        $campaigns->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Liste des campagnes de réduction.',
            'data' => $campaigns->items(),
            'pagination' => $this->campaignSearchService->paginationMeta($campaigns),
        ]);
    }

    public function byType(string $establishmentType)
    {
        if (!in_array($establishmentType, ['lavage', 'station', 'etablissement'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Type d’établissement invalide.',
            ], 422);
        }

        $campaigns = $this->campaignSearchService->activeCampaignQuery()
            ->where('establishment_type', $establishmentType)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ReductionCampaign $campaign) {
                return $this->campaignSearchService->formatCampaign($campaign);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Liste des campagnes de réduction par type d’établissement.',
            'data' => $campaigns,
        ]);
    }

    public function byEstablishment(string $establishmentType, int $establishmentId)
    {
        if (!in_array($establishmentType, ['lavage', 'station', 'etablissement'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Type d’établissement invalide.',
            ], 422);
        }

        $campaigns = $this->campaignSearchService->activeCampaignQuery()
            ->where('establishment_type', $establishmentType)
            ->where('establishment_id', $establishmentId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ReductionCampaign $campaign) {
                return $this->campaignSearchService->formatCampaign($campaign);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Liste des campagnes de réduction de l’établissement.',
            'data' => $campaigns,
        ]);
    }

    public function show(int $campaign)
    {
        $campaign = $this->campaignSearchService->activeCampaignQuery()->where('id', $campaign)->first();

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campagne de réduction introuvable ou indisponible.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détail de la campagne de réduction.',
            'data' => $this->campaignSearchService->formatCampaign($campaign),
        ]);
    }
}
