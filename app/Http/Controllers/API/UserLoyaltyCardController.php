<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserLoyaltyCardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'matricule_vehicule' => ['nullable', 'string', 'max:50'],
            'lavage_id' => ['nullable', 'integer', 'min:1'],
            'statut' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur usager non authentifié.',
            ], 401);
        }

        $query = DB::table('fidelites')
            ->where('usager_id', $user->id);

        if (!empty($validated['matricule_vehicule'])) {
            $query->where('matricule_vehicule', $validated['matricule_vehicule']);
        }

        if (!empty($validated['lavage_id'])) {
            $query->where('lavage_id', $validated['lavage_id']);
        }

        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $hasStationColumn = Schema::hasColumn('fidelites', 'station_lavage_id');
        $cards = $query->orderByDesc('id')->get();

        if ($cards->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aucune carte de fidélité trouvée.',
                'data' => [],
            ]);
        }

        $lavageIds = $cards->pluck('lavage_id')->filter()->unique()->values();
        $lavages = DB::table('lavages')
            ->whereIn('id', $lavageIds)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        $stations = collect();
        if ($hasStationColumn && Schema::hasTable('station_de_lavages')) {
            $stationIds = $cards->pluck('station_lavage_id')->filter()->unique()->values();
            $stations = DB::table('station_de_lavages')
                ->whereIn('id', $stationIds)
                ->get(['id', 'name'])
                ->keyBy('id');
        }

        $passagesByCard = collect();
        if (Schema::hasTable('fidelite_passages')) {
            $passages = DB::table('fidelite_passages as passages')
                ->leftJoin('type_lavages as types', 'types.id', '=', 'passages.type_lavage_id')
                ->whereIn('passages.fidelite_id', $cards->pluck('id'))
                ->orderByDesc('passages.used_at')
                ->orderByDesc('passages.id')
                ->get([
                    'passages.id',
                    'passages.fidelite_id',
                    'passages.used_at',
                    'passages.type_lavage_id',
                    'types.libelle as type_lavage_libelle',
                    'passages.attribution_vehicule_id',
                    'passages.source',
                ]);

            $passagesByCard = $passages->groupBy('fidelite_id');
        }

        $data = $cards->map(function ($card) use ($lavages, $stations, $passagesByCard, $hasStationColumn) {
            $casesRemplies = max(0, (int) ($card->cases_remplies ?? 0));
            $totalCases = max(0, (int) ($card->total_cases ?? 0));
            $lavage = $lavages->get($card->lavage_id);
            $station = $hasStationColumn ? $stations->get($card->station_lavage_id) : null;
            $passages = $passagesByCard->get($card->id, collect());

            return [
                'id' => (int) $card->id,
                'lavage_id' => $card->lavage_id ? (int) $card->lavage_id : null,
                'lavage' => $lavage ? [
                    'id' => (int) $lavage->id,
                    'nom' => trim(($lavage->first_name ?? '') . ' ' . ($lavage->last_name ?? '')),
                ] : null,
                'station_lavage_id' => $station ? (int) $station->id : null,
                'station_lavage' => $station ? [
                    'id' => (int) $station->id,
                    'nom' => $station->name,
                ] : null,
                'matricule_vehicule' => $card->matricule_vehicule,
                'statut' => $card->statut ?? 'en_cours',
                'cases_remplies' => $casesRemplies,
                'total_cases' => $totalCases,
                'cases_restantes' => max(0, $totalCases - $casesRemplies),
                'progression' => $totalCases > 0
                    ? min(100, round(($casesRemplies / $totalCases) * 100, 2))
                    : 0,
                'recompenses_gagnees' => isset($card->recompenses_gagnees)
                    ? (int) $card->recompenses_gagnees
                    : null,
                'derniere_recompense' => $card->derniere_recompense ?? null,
                'created_at' => $card->created_at ?? null,
                'completed_at' => $card->completed_at ?? null,
                'passages' => $passages->map(function ($passage) {
                    return [
                        'id' => (int) $passage->id,
                        'used_at' => $passage->used_at,
                        'type_lavage' => $passage->type_lavage_id ? [
                            'id' => (int) $passage->type_lavage_id,
                            'libelle' => $passage->type_lavage_libelle,
                        ] : null,
                        'attribution_vehicule_id' => $passage->attribution_vehicule_id
                            ? (int) $passage->attribution_vehicule_id
                            : null,
                        'source' => $passage->source,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Cartes de fidélité récupérées avec succès.',
            'data' => $data,
        ]);
    }
}
