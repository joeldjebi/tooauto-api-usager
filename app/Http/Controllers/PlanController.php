<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AbonnementUsager;
use App\Models\Categorie_service;
use App\Models\ForfaitUsager;
use App\Models\Info;
use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\WasabiService;

class PlanController extends Controller
{
	protected $wasabiService;

	public function __construct(WasabiService $wasabiService)
	{
		$this->wasabiService = $wasabiService;
	}

    /**
     * Display a listing of the resource.
     */
	public function apiForfaitsAvecAvantages()
	{
		$forfaits = ForfaitUsager::with('avantageUsager')->get();

		$forfaits = $forfaits->map(function($forfait) {
			$data = $forfait->toArray();
			unset($data['avantage_usager']);

			$avantages = $forfait->avantageUsager ? $this->formatAvantages($forfait->avantageUsager->avantages) : [];

			$data['forfait_avantage_usager_id'] = $forfait->forfait_avantage_usager_id;
			$data['avantages'] = $avantages;
			$data['forfait_avantage_usager'] = $forfait->avantageUsager ? array_merge(
				$forfait->avantageUsager->toArray(),
				['avantages' => $avantages]
			) : null;

			return $data;
		});

		return response()->json([
			'success' => true,
			'data' => $forfaits
		]);
	}

	public function apiCategoriesServicesByAbonnement(Request $request)
	{
		$user = auth()->user();

		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => 'Utilisateur non authentifié.',
			], 401);
		}

		$abonnement = AbonnementUsager::where('user_id', $user->id)
			->where('statut', 1)
			->with([
				'forfait.categorieServices.sousCategorieServices.ssCategorieServices',
				'forfait.categorieServices.sousCategorieServices.ssCategorieService',
				'forfait.categorieServices.sousCategorieService.ssCategorieService',
			])
			->latest()
			->first();

		$forfait = $abonnement ? $abonnement->forfait : null;
		$abonnementExpire = $abonnement && $abonnement->date_fin && $abonnement->date_fin->isPast();
		$abonnementActif = $abonnement && $forfait && !$abonnementExpire;
		$forfaitCategorieIds = $forfait ? $forfait->categorieServices->pluck('id')->map(function ($id) {
			return (int) $id;
		})->toArray() : [];

		$categorieServices = Categorie_service::with([
				'sousCategorieServices.ssCategorieServices',
				'sousCategorieServices.ssCategorieService',
				'sousCategorieService.ssCategorieService',
			])
			->where('statut', 1)
			->orderBy('id')
			->get()
			->filter(function ($categorie) use ($abonnementActif, $abonnementExpire, $forfaitCategorieIds) {
				$categorieDansForfait = in_array((int) $categorie->id, $forfaitCategorieIds, true);

				return $categorie->visible_par_defaut
					|| ($abonnementActif && $categorieDansForfait)
					|| ($abonnementExpire && $categorie->accessible_abonnement_expire);
			})
			->when($request->filled('categorie_service_id'), function ($categories) use ($request) {
				return $categories->where('id', (int) $request->categorie_service_id);
			})
			->when($request->filled('sous_categorie_service_id'), function ($categories) use ($request) {
				return $categories->filter(function ($categorie) use ($request) {
					return $this->categorieHasSousCategorie($categorie, (int) $request->sous_categorie_service_id);
				});
			})
			->when($request->filled('ss_categorie_service_id'), function ($categories) use ($request) {
				return $categories->filter(function ($categorie) use ($request) {
					return $this->categorieHasSsCategorie($categorie, (int) $request->ss_categorie_service_id);
				});
			})
			->values()
			->map(function ($categorie) use ($abonnementActif, $abonnementExpire, $forfaitCategorieIds) {
				$categorieDansForfait = in_array((int) $categorie->id, $forfaitCategorieIds, true);
				$accessible = $this->isCategorieAccessible($categorie, $abonnementActif, $abonnementExpire, $categorieDansForfait);

				return $this->formatCategorieService($categorie, true, $accessible, $categorieDansForfait);
			});

		return response()->json([
			'success' => true,
			'message' => "Liste des catégories de services de l'abonnement.",
			'abonnement' => $abonnement ? $this->formatAbonnement($abonnement) : null,
			'forfait' => $forfait ? [
				'id' => $forfait->id,
				'nom' => $forfait->libelle,
				'libelle' => $forfait->libelle,
				'duree' => $forfait->duree,
				'prix' => $forfait->prix,
				'statut' => $forfait->statut,
			] : null,
			'filtres' => [
				'categorie_service_id' => $request->categorie_service_id,
				'sous_categorie_service_id' => $request->sous_categorie_service_id,
				'ss_categorie_service_id' => $request->ss_categorie_service_id,
			],
			'categorie_services' => $categorieServices,
		], 200);
	}

	private function isCategorieAccessible($categorie, $abonnementActif, $abonnementExpire, $categorieDansForfait)
	{
		if ($abonnementExpire && $categorie->accessible_abonnement_expire) {
			return true;
		}

		if ($categorie->accessible_en_fonction_de_mon_abonnement_actif) {
			return $abonnementActif && $categorieDansForfait;
		}

		return true;
	}

	private function formatAvantages($avantages)
	{
		return array_values(array_filter(array_map('trim', explode(';', $avantages ?? ''))));
	}

	private function formatAbonnement($abonnement)
	{
		return [
			'id' => $abonnement->id,
			'forfait_id' => $abonnement->forfait_id,
			'date_debut' => $abonnement->date_debut ? $abonnement->date_debut->format('Y-m-d') : null,
			'date_fin' => $abonnement->date_fin ? $abonnement->date_fin->format('Y-m-d') : null,
			'is_free' => (bool) $abonnement->is_free,
			'statut' => $abonnement->statut,
			'est_expire' => $abonnement->date_fin ? $abonnement->date_fin->isPast() : false,
		];
	}

	private function formatCategorieService($categorie, $visible = true, $accessible = true, $categorieDansForfait = false)
	{
		$sousCategorieServices = $this->getCategorieSousCategories($categorie);

		$data = [
			'id' => $categorie->id,
			'libelle' => $categorie->libelle,
			'image' => !empty($categorie->image) ? $this->wasabiService->temporaryUrl($categorie->image) : null,
			'statut' => $categorie->statut,
			'is_pro' => $categorie->is_pro,
			'pro_or_usager' => $categorie->pro_or_usager,
			'sous_categorie_service_id' => $categorie->sous_categorie_service_id,
			'visible_par_defaut' => (bool) $categorie->visible_par_defaut,
			'accessible_abonnement_expire' => (bool) $categorie->accessible_abonnement_expire,
			'accessible_en_fonction_de_mon_abonnement_actif' => (bool) $categorie->accessible_en_fonction_de_mon_abonnement_actif,
			'visible' => (bool) $visible,
			'accessible' => (bool) $accessible,
			'dans_mon_forfait' => (bool) $categorieDansForfait,
		];

		$data['sous_categorie_services'] = $sousCategorieServices
			->map(function ($sousCategorie) {
				return $this->formatSousCategorieService($sousCategorie);
			})
			->values();

		$data['sous_categorie_service'] = $data['sous_categorie_services']->first();

		return $data;
	}

	private function formatSousCategorieService($sousCategorie)
	{
		if (!$sousCategorie) {
			return null;
		}

		$ssCategorieServices = $this->getSousCategorieSsCategories($sousCategorie);

		$data = [
			'id' => $sousCategorie->id,
			'libelle' => $sousCategorie->libelle,
			'image' => !empty($sousCategorie->image) ? $this->wasabiService->temporaryUrl($sousCategorie->image) : null,
			'statut' => $sousCategorie->statut ?? null,
			'is_pro' => $sousCategorie->is_pro ?? null,
			'pro_or_usager' => $sousCategorie->pro_or_usager ?? null,
			'ss_categorie_service_id' => $sousCategorie->ss_categorie_service_id ?? null,
		];

		$data['ss_categorie_services'] = $ssCategorieServices
			->map(function ($ssCategorie) {
				return $this->formatSsCategorieService($ssCategorie);
			})
			->values();

		$data['ss_categorie_service'] = $data['ss_categorie_services']->first();

		return $data;
	}

	private function getCategorieSousCategories($categorie)
	{
		$sousCategories = $categorie->relationLoaded('sousCategorieServices')
			? $categorie->sousCategorieServices
			: collect();

		if ($sousCategories->isEmpty() && $categorie->sousCategorieService) {
			$sousCategories = collect([$categorie->sousCategorieService]);
		}

		return $sousCategories->filter()->values();
	}

	private function getSousCategorieSsCategories($sousCategorie)
	{
		$ssCategories = $sousCategorie->relationLoaded('ssCategorieServices')
			? $sousCategorie->ssCategorieServices
			: collect();

		if ($ssCategories->isEmpty() && $sousCategorie->ssCategorieService) {
			$ssCategories = collect([$sousCategorie->ssCategorieService]);
		}

		return $ssCategories->filter()->values();
	}

	private function categorieHasSousCategorie($categorie, $sousCategorieId)
	{
		return $this->getCategorieSousCategories($categorie)
			->contains(function ($sousCategorie) use ($sousCategorieId) {
				return (int) $sousCategorie->id === $sousCategorieId;
			});
	}

	private function categorieHasSsCategorie($categorie, $ssCategorieId)
	{
		return $this->getCategorieSousCategories($categorie)
			->contains(function ($sousCategorie) use ($ssCategorieId) {
				return $this->getSousCategorieSsCategories($sousCategorie)
					->contains(function ($ssCategorie) use ($ssCategorieId) {
						return (int) $ssCategorie->id === $ssCategorieId;
					});
			});
	}

	private function formatSsCategorieService($ssCategorie)
	{
		if (!$ssCategorie) {
			return null;
		}

		return [
			'id' => $ssCategorie->id,
			'libelle' => $ssCategorie->libelle,
			'image' => !empty($ssCategorie->image) ? $this->wasabiService->temporaryUrl($ssCategorie->image) : null,
			'statut' => $ssCategorie->statut ?? null,
			'is_pro' => $ssCategorie->is_pro ?? null,
			'pro_or_usager' => $ssCategorie->pro_or_usager ?? null,
		];
	}
	
	
}
