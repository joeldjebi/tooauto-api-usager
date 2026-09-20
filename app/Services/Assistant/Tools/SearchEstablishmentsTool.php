<?php

namespace App\Services\Assistant\Tools;

use App\Models\Etablissement;
use App\Models\Station_service;
use App\Models\StationDeLavage;
use App\Services\Assistant\Contracts\AssistantTool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchEstablishmentsTool implements AssistantTool
{
    public function name(): string
    {
        return 'search_establishments';
    }

    public function description(): string
    {
        return "Recherche des établissements (garages, agents de constat, cabinets d'expertise, etc.), "
            . "stations-service ou stations de lavage. Utilise cet outil dès qu'un usager cherche un "
            . "professionnel ou un lieu, éventuellement trié par proximité si sa position est connue.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Texte libre à rechercher dans le nom.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['etablissement', 'lavage', 'station'],
                    'description' => "Grande catégorie technique : \"lavage\" = station de lavage, "
                        . "\"station\" = station-service (carburant), \"etablissement\" = tout le reste "
                        . "(garages, agents de constat, cabinets d'expertise, vendeurs de pièces...). "
                        . "Omettre pour chercher dans les trois. Ne jamais y mettre un métier précis "
                        . "(garage, constat...) : utiliser \"category\" pour ça.",
                ],
                'category' => [
                    'type' => 'string',
                    'description' => "Métier ou profession précis recherché (ex: \"agent de constat\", "
                        . "\"garage\", \"cabinet d'expertise\", \"vulcanisateur\"). S'applique uniquement "
                        . "quand \"type\" vaut \"etablissement\" ou est omis.",
                ],
            ],
            'required' => [],
        ];
    }

    public function handle(array $input, $authenticatable, string $guard, array $context = []): array
    {
        $search = $input['search'] ?? null;
        $type = $input['type'] ?? null;
        $category = $input['category'] ?? null;

        // Repli défensif : si le modèle envoie une valeur hors de l'enum (ex: un métier précis
        // confondu avec le type), on l'ignore plutôt que de retourner zéro résultat.
        if (!in_array($type, ['etablissement', 'lavage', 'station'], true)) {
            if ($type && !$category) {
                $category = $type;
            }

            $type = null;
        }
        $latitude = is_numeric($context['latitude'] ?? null) ? (float) $context['latitude'] : null;
        $longitude = is_numeric($context['longitude'] ?? null) ? (float) $context['longitude'] : null;
        $maxResults = (int) config('services.assistant.max_results', 10);

        $results = new Collection();

        if (!$type || $type === 'etablissement') {
            $results = $results->merge($this->searchEtablissements($search, $category, $latitude, $longitude, $maxResults));
        }

        if (!$type || $type === 'lavage') {
            $results = $results->merge($this->searchTable(StationDeLavage::query(), 'lavage', $search, $latitude, $longitude, $maxResults));
        }

        if (!$type || $type === 'station') {
            $results = $results->merge($this->searchTable(Station_service::query(), 'station', $search, $latitude, $longitude, $maxResults));
        }

        if ($latitude !== null && $longitude !== null) {
            $results = $results->sortBy('distance_km');
        }

        $results = $results->take($maxResults)->values();

        return [
            'total' => $results->count(),
            'establishments' => $results->all(),
        ];
    }

    private function searchEtablissements(?string $search, ?string $category, ?float $latitude, ?float $longitude, int $limit): Collection
    {
        $query = Etablissement::query()
            ->where('statut', 1)
            ->with(['type_etablissement', 'ville', 'commune']);

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($category) {
            $query->whereHas('type_etablissement', function (Builder $q) use ($category) {
                $q->where('libelle', 'like', '%' . $category . '%');
            });
        }

        $this->applyDistance($query, $latitude, $longitude);
        $this->applySort($query, $latitude, $longitude);

        return $query->limit($limit)->get()->map(function (Etablissement $etablissement) {
            $phone = $this->formatPhone($etablissement->mobile ?: $etablissement->mobile_fix, $etablissement->indicatif);

            return [
                'id' => $etablissement->id,
                'name' => $etablissement->name,
                'type' => 'etablissement',
                'category' => $etablissement->type_etablissement->libelle ?? null,
                'adresse' => $etablissement->adresse,
                'commune' => $etablissement->commune->nom ?? null,
                'ville' => $etablissement->ville->libelle ?? null,
                'distance_km' => $this->distanceKm($etablissement),
                'mobile' => $phone['display'] ?? null,
                'phone_url' => $phone['url'] ?? null,
                'maps_url' => $this->mapsUrl($etablissement->latitude, $etablissement->longitude),
            ];
        });
    }

    private function searchTable(Builder $query, string $type, ?string $search, ?float $latitude, ?float $longitude, int $limit): Collection
    {
        $query->where('statut', 1);

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $this->applyDistance($query, $latitude, $longitude);
        $this->applySort($query, $latitude, $longitude);

        return $query->limit($limit)->get()->map(function ($model) use ($type) {
            // "contact" pour les stations de lavage, "mobile" pour les stations-service :
            // les deux tables n'ont pas le même nom de colonne téléphone.
            $rawPhone = $type === 'lavage' ? $model->contact : $model->mobile;
            $phone = $this->formatPhone($rawPhone, null);

            return [
                'id' => $model->id,
                'name' => $model->name,
                'type' => $type,
                'category' => null,
                'adresse' => $model->adresse,
                'commune' => null,
                'ville' => null,
                'distance_km' => $this->distanceKm($model),
                'mobile' => $phone['display'] ?? null,
                'phone_url' => $phone['url'] ?? null,
                'maps_url' => $this->mapsUrl($model->latitude, $model->longitude),
            ];
        });
    }

    private function applyDistance(Builder $query, ?float $latitude, ?float $longitude): void
    {
        if ($latitude === null || $longitude === null) {
            return;
        }

        $query->selectRaw("*, (
            CASE
                WHEN longitude IS NULL OR latitude IS NULL THEN NULL
                ELSE (
                    6371 * acos(
                        cos(radians(?)) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(latitude))
                    )
                )
            END
        ) as distance", [$latitude, $longitude, $latitude]);
    }

    private function applySort(Builder $query, ?float $latitude, ?float $longitude): void
    {
        if ($latitude !== null && $longitude !== null) {
            $query->orderByRaw('distance IS NULL, distance ASC');
        } else {
            $query->orderByDesc('id');
        }
    }

    private function distanceKm($model): ?float
    {
        return isset($model->distance) ? round((float) $model->distance, 1) : null;
    }

    /**
     * Normalise un numéro local en lien "tel:" cliquable.
     * Convention déjà utilisée ailleurs dans le projet (ex: AuthController) :
     * l'indicatif est simplement concaténé devant le numéro local (zéro initial conservé).
     */
    private function formatPhone(?string $raw, ?string $indicatif): ?array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $raw);

        // Convention CI déjà utilisée dans le projet : le zéro initial du numéro local
        // est conservé, l'indicatif est juste préfixé devant (pas de retrait du 0).
        $e164 = str_starts_with($digits, '+') ? $digits : '+' . ($indicatif ?: '225') . $digits;

        return [
            'display' => $e164,
            'url' => 'tel:' . $e164,
        ];
    }

    private function mapsUrl($latitude, $longitude): ?string
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        // Écarte les coordonnées aberrantes (données de test ou champs mal remplis en base).
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
    }
}
