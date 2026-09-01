<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReductionCampaign;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserCampaignController extends Controller
{
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
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $campaigns = $this->activeCampaignQuery()
            ->when(!empty($validated['establishment_type']), function (Builder $query) use ($validated) {
                $query->where('establishment_type', $validated['establishment_type']);
            })
            ->when(!empty($validated['establishment_id']), function (Builder $query) use ($validated) {
                $query->where('establishment_id', $validated['establishment_id']);
            })
            ->when(!empty($validated['search']), function (Builder $query) use ($validated) {
                $search = '%' . $validated['search'] . '%';
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('product_or_service', 'like', $search)
                        ->orWhere('conditions', 'like', $search);
                });
            })
            ->when(!empty($validated['date_debut']), function (Builder $query) use ($validated) {
                $query->whereDate('date_debut', '>=', $validated['date_debut']);
            })
            ->when(!empty($validated['date_fin']), function (Builder $query) use ($validated) {
                $query->whereDate('date_fin', '<=', $validated['date_fin']);
            })
            ->when(!empty($validated['discount_type']), function (Builder $query) use ($validated) {
                $query->where('discount_type', $validated['discount_type']);
            })
            ->when(isset($validated['min_price']), function (Builder $query) use ($validated) {
                $query->where('promotional_price', '>=', $validated['min_price']);
            })
            ->when(isset($validated['max_price']), function (Builder $query) use ($validated) {
                $query->where('promotional_price', '<=', $validated['max_price']);
            })
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 15);

        $campaigns->getCollection()->transform(function (ReductionCampaign $campaign) {
            return $this->formatCampaign($campaign);
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des campagnes de réduction disponibles.',
            'data' => $campaigns,
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

        $campaigns = $this->activeCampaignQuery()
            ->where('establishment_type', $establishmentType)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ReductionCampaign $campaign) {
                return $this->formatCampaign($campaign);
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

        $campaigns = $this->activeCampaignQuery()
            ->where('establishment_type', $establishmentType)
            ->where('establishment_id', $establishmentId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ReductionCampaign $campaign) {
                return $this->formatCampaign($campaign);
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
        $campaign = $this->activeCampaignQuery()->where('id', $campaign)->first();

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campagne de réduction introuvable ou indisponible.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détail de la campagne de réduction.',
            'data' => $this->formatCampaign($campaign),
        ]);
    }

    private function activeCampaignQuery(): Builder
    {
        $today = Carbon::today();

        return ReductionCampaign::query()
            ->where('statut', 1)
            ->whereDate('date_debut', '<=', $today)
            ->whereDate('date_fin', '>=', $today)
            ->where(function (Builder $query) {
                $query->whereNull('quantity_available')
                    ->orWhere(function (Builder $query) {
                        $query->whereNull('quantity_used')
                            ->orWhereColumn('quantity_used', '<', 'quantity_available');
                    });
            });
    }

    private function formatCampaign(ReductionCampaign $campaign): array
    {
        $prestations = $this->resolveCampaignPrestations($campaign);

        return [
            'id' => $campaign->id,
            'establishment_type' => $campaign->establishment_type,
            'establishment_id' => $campaign->establishment_id,
            'name' => $campaign->name,
            'image' => $campaign->image,
            'image_url' => $this->imageUrl($campaign->image),
            'description' => $campaign->description,
            'product_or_service' => $campaign->product_or_service,
            'product_or_service_ids' => $prestations['ids'],
            'product_or_service_libelles' => $prestations['libelles'],
            'prestations' => $prestations['prestations'],
            'discount_type' => $campaign->discount_type,
            'discount_value' => (float) $campaign->discount_value,
            'normal_price' => $campaign->normal_price !== null ? (float) $campaign->normal_price : null,
            'promotional_price' => $campaign->promotional_price !== null ? (float) $campaign->promotional_price : null,
            'montant_reduction' => $this->montantReduction($campaign),
            'date_debut' => optional($campaign->date_debut)->toDateString(),
            'date_fin' => optional($campaign->date_fin)->toDateString(),
            'quantity_available' => $campaign->quantity_available,
            'quantity_used' => $campaign->quantity_used,
            'quantity_remaining' => $this->quantityRemaining($campaign),
            'conditions' => $campaign->conditions,
            'statut' => $campaign->statut,
            'created_at' => optional($campaign->created_at)->toDateTimeString(),
            'updated_at' => optional($campaign->updated_at)->toDateTimeString(),
        ];
    }

    private function resolveCampaignPrestations(ReductionCampaign $campaign): array
    {
        $raw = trim((string) $campaign->product_or_service);

        if ($raw === '') {
            return [
                'ids' => [],
                'libelles' => [],
                'prestations' => [],
            ];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
        $isIdList = count($parts) > 0 && collect($parts)->every(function ($part) {
            return ctype_digit($part);
        });

        if (!$isIdList) {
            return [
                'ids' => [],
                'libelles' => $parts,
                'prestations' => [],
            ];
        }

        $ids = array_values(array_unique(array_map('intval', $parts)));
        $table = $this->prestationTableForType($campaign->establishment_type);

        if (!$table || !Schema::hasTable($table)) {
            return [
                'ids' => $ids,
                'libelles' => [],
                'prestations' => [],
            ];
        }

        $records = DB::table($table)->whereIn('id', $ids);
        $this->applyEstablishmentFilter($records, $table, $campaign);
        $records = $records->get()->keyBy('id');

        $prestations = [];
        foreach ($ids as $id) {
            if (!$records->has($id)) {
                continue;
            }

            $record = $records->get($id);
            $prestations[] = [
                'id' => $record->id,
                'libelle' => $this->recordValue($record, ['libelle', 'name']),
                'montant' => $this->recordValue($record, ['montant', 'prix']),
            ];
        }

        return [
            'ids' => $ids,
            'libelles' => collect($prestations)->pluck('libelle')->filter()->values()->all(),
            'prestations' => $prestations,
        ];
    }

    private function prestationTableForType(?string $establishmentType): ?string
    {
        return match ($establishmentType) {
            'lavage' => 'type_lavages',
            'station' => 'type_prestation_station_services',
            default => 'type_de_prestations',
        };
    }

    private function applyEstablishmentFilter($query, string $table, ReductionCampaign $campaign): void
    {
        $candidateColumns = match ($campaign->establishment_type) {
            'lavage' => ['lavage_id'],
            'station' => ['station_id', 'station_service_id', 'establishment_id'],
            default => ['etablissement_id'],
        };

        foreach ($candidateColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $campaign->establishment_id);
                return;
            }
        }
    }

    private function recordValue(object $record, array $columns)
    {
        foreach ($columns as $column) {
            if (property_exists($record, $column)) {
                return $record->{$column};
            }
        }

        return null;
    }

    private function montantReduction(ReductionCampaign $campaign): float
    {
        $normalPrice = $campaign->normal_price !== null ? (float) $campaign->normal_price : null;
        $promotionalPrice = $campaign->promotional_price !== null ? (float) $campaign->promotional_price : null;

        if ($normalPrice !== null && $promotionalPrice !== null) {
            return round(max(0, $normalPrice - $promotionalPrice), 2);
        }

        if ($normalPrice === null) {
            return 0;
        }

        if ($campaign->discount_type === 'percentage') {
            return round(($normalPrice * (float) $campaign->discount_value) / 100, 2);
        }

        if ($campaign->discount_type === 'fixed') {
            return round(min((float) $campaign->discount_value, $normalPrice), 2);
        }

        return 0;
    }

    private function quantityRemaining(ReductionCampaign $campaign): ?int
    {
        if ($campaign->quantity_available === null) {
            return null;
        }

        return max(0, (int) $campaign->quantity_available - (int) $campaign->quantity_used);
    }

    private function imageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        return Str::startsWith($image, '/')
            ? url($image)
            : url('/' . ltrim($image, '/'));
    }
}
