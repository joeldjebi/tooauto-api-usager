<?php

namespace App\Services;

use App\Models\Etablissement;
use App\Models\ReductionCampaign;
use App\Models\Station_service;
use App\Models\StationDeLavage;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CampaignSearchService
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    public function search(array $filters): LengthAwarePaginator
    {
        $availableOnly = (bool) ($filters['available_only'] ?? false);

        $campaigns = ReductionCampaign::query()
            ->when($availableOnly, function (Builder $query) {
                $this->applyAvailableCampaignRules($query);
            })
            ->when(!empty($filters['establishment_type']), function (Builder $query) use ($filters) {
                $query->where('establishment_type', $filters['establishment_type']);
            })
            ->when(!empty($filters['establishment_id']), function (Builder $query) use ($filters) {
                $query->where('establishment_id', $filters['establishment_id']);
            })
            ->when(!empty($filters['search']), function (Builder $query) use ($filters) {
                $search = '%' . $filters['search'] . '%';
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('product_or_service', 'like', $search)
                        ->orWhere('conditions', 'like', $search);
                });
            })
            ->when(!empty($filters['date_debut']), function (Builder $query) use ($filters) {
                $query->whereDate('date_debut', '>=', $filters['date_debut']);
            })
            ->when(!empty($filters['date_fin']), function (Builder $query) use ($filters) {
                $query->whereDate('date_fin', '<=', $filters['date_fin']);
            })
            ->when(!empty($filters['discount_type']), function (Builder $query) use ($filters) {
                $query->where('discount_type', $filters['discount_type']);
            })
            ->when(isset($filters['min_price']), function (Builder $query) use ($filters) {
                $query->where('promotional_price', '>=', $filters['min_price']);
            })
            ->when(isset($filters['max_price']), function (Builder $query) use ($filters) {
                $query->where('promotional_price', '<=', $filters['max_price']);
            })
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);

        $campaigns->getCollection()->transform(function (ReductionCampaign $campaign) {
            return $this->formatCampaign($campaign);
        });

        return $campaigns;
    }

    public function activeCampaignQuery(): Builder
    {
        return $this->applyAvailableCampaignRules(ReductionCampaign::query());
    }

    public function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ];
    }

    public function formatCampaign(ReductionCampaign $campaign): array
    {
        $prestations = $this->resolveCampaignPrestations($campaign);

        return [
            'id' => $campaign->id,
            'establishment_type' => $campaign->establishment_type,
            'establishment_id' => $campaign->establishment_id,
            'establishment' => $this->resolveCampaignEstablishment($campaign),
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

    private function applyAvailableCampaignRules(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query->where('statut', 1)
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

    private function resolveCampaignEstablishment(ReductionCampaign $campaign): ?array
    {
        $establishmentId = (int) $campaign->establishment_id;

        if ($establishmentId <= 0) {
            return null;
        }

        $establishment = match ($campaign->establishment_type) {
            'lavage' => StationDeLavage::with('typeLavages')->find($establishmentId),
            'station' => Station_service::with(['ville', 'commune'])->find($establishmentId),
            default => Etablissement::with(['type_etablissement', 'pays', 'ville', 'commune'])->find($establishmentId),
        };

        if (!$establishment) {
            return null;
        }

        $data = $establishment->toArray();

        if (!empty($data['logo'])) {
            $data['logo_url'] = $this->signedImageUrl($data['logo']);
        }

        if (!empty($data['cover'])) {
            $data['cover_url'] = $this->signedImageUrl($data['cover']);
        }

        return $data;
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
        return $this->signedImageUrl($image);
    }

    private function signedImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        try {
            return $this->wasabiService->temporaryUrl(ltrim($image, '/'));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
