<?php

namespace App\Http\Controllers;

use App\Models\EntrepriseAssurance;
use App\Services\WasabiService;

class EntrepriseAssuranceController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    /**
     * Retourne la liste des entreprises d'assurance.
     */
    public function index()
    {
        $entreprises = EntrepriseAssurance::orderBy('nom', 'asc')->get();

        if ($entreprises->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucune entreprise d'assurance enregistrée pour le moment.",
            ], 404);
        }

        $entreprises->transform(function ($entreprise) {
            $entreprise->logo_url = $this->getLogoUrl($entreprise->logo);

            return $entreprise;
        });

        return response()->json([
            'success' => true,
            'message' => "Liste des entreprises d'assurance.",
            'entreprises_assurances' => $entreprises,
        ], 200);
    }

    private function getLogoUrl(?string $logo): ?string
    {
        if (empty($logo)) {
            return null;
        }

        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return $logo;
        }

        try {
            return $this->wasabiService->temporaryUrl($logo) ?? $logo;
        } catch (\Throwable $e) {
            return $logo;
        }
    }
}
