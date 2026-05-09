<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\shareLocalisation;
use App\Models\Article;
use App\Models\TypeEtablissement;
use App\Models\Cabinet_expertise;
use App\Models\Promotion;
use App\Models\Commissariat;
use App\Models\Categorie_service;
use App\Models\Station_service;
use App\Models\Commune;
use App\Models\Pays;
use App\Models\Ville;
use App\Models\Service;
use App\Models\Sapeur_pompier;
use App\Models\Type_de_prestation;
use App\Models\TypeDePrestation;
use App\Models\Type_de_piece;
use App\Models\Type_de_demande;
use Validator;
use App\Models\Type_alert;
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


class EtablissementController extends Controller
{
    protected $wasabiService;

    public function __construct(WasabiService $wasabiService)
    {
        $this->wasabiService = $wasabiService;
    }

    /**
     * Display a listing of the resource.
     */
    /*public function index(Request $request)
    {
        // Récupérer les établissements triés par ID décroissant
        $etablissements = Etablissement::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($etablissements->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun établissement enregistré pour le moment.',
            ], 404);
        }

        // Récupérer tous les types de prestations en une seule requête
        $allTypesPrestations = TypeDePrestation::all()->keyBy('id');

        // Ajouter les libellés des types de prestations à chaque établissement
        $etablissements->transform(function ($etablissement) use ($allTypesPrestations) {
            // Méthode simple et directe
            $etablissement->types_prestations_libelles = $this->getTypesPrestationsLibelles($etablissement->type_de_prestations, $allTypesPrestations);
            $etablissement->types_prestations_complets = $this->getTypesPrestationsComplets($etablissement->type_de_prestations, $allTypesPrestations);

            // Ajouter l'indicatif '+225' au champ mobile s'il existe
            if (!empty($etablissement->mobile)) {
                $etablissement->mobile = $etablissement->mobile;
            }

            $etablissement = $this->attachEtablissementMediaUrls($etablissement);

            return $etablissement;
        });

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des établissements.',
            'etablissements' => $etablissements,
        ], 200);
    }*/
	public function index(Request $request)
	{
		// Récupérer les établissements avec la moyenne des notes
		$etablissements = Etablissement::withAvg('notations as moyenne_note', 'note')
			->orderBy('id', 'desc')
			->get();

		// Vérifier si des établissements existent
		if ($etablissements->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'Aucun établissement enregistré pour le moment.',
			], 404);
		}

		// Récupérer tous les types de prestations en une seule requête
		$allTypesPrestations = TypeDePrestation::all()->keyBy('id');

		// Transformer les données
		$etablissements->transform(function ($etablissement) use ($allTypesPrestations) {

			// ✅ Moyenne des notes (toujours float)
			$etablissement->moyenne_note = $etablissement->moyenne_note !== null
				? (float) round($etablissement->moyenne_note, 1)
				: 0.0;

			// Libellés des types de prestations
			$etablissement->types_prestations_libelles = $this->getTypesPrestationsLibelles(
				$etablissement->type_de_prestations,
				$allTypesPrestations
			);

			// Types de prestations complets
			$etablissement->types_prestations_complets = $this->getTypesPrestationsComplets(
				$etablissement->type_de_prestations,
				$allTypesPrestations
			);

			// Mobile
			if (!empty($etablissement->mobile)) {
				$etablissement->mobile = $etablissement->mobile;
			}

			// Médias
			$etablissement = $this->attachEtablissementMediaUrls($etablissement);

			return $etablissement;
		});

		// Retour JSON
		return response()->json([
			'success' => true,
			'message' => 'Liste des établissements.',
			'etablissements' => $etablissements,
		], 200);
	}

    private function attachEtablissementMediaUrls($etablissement)
    {
        if (!$etablissement) {
            return $etablissement;
        }

        $etablissement->logo = !empty($etablissement->logo)
            ? $this->wasabiService->temporaryUrl($this->normalizeEtablissementMediaPath($etablissement->logo, 'logo'))
            : null;

        $etablissement->cover = !empty($etablissement->cover)
            ? $this->wasabiService->temporaryUrl($this->normalizeEtablissementMediaPath($etablissement->cover, 'cover'))
            : null;

        return $etablissement;
    }

    private function normalizeEtablissementMediaPath($path, $type)
    {
        if (empty($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (Str::contains($path, '/')) {
            return $path;
        }

        return 'etablissement/' . trim($type, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Récupérer les libellés des types de prestations
     */
    private function getTypesPrestationsLibelles($typeDePrestationsJson, $allTypesPrestations)
    {
        if (empty($typeDePrestationsJson)) {
            return [];
        }

        $ids = json_decode($typeDePrestationsJson, true);
        if (!is_array($ids)) {
            return [];
        }

        $libelles = [];
        foreach ($ids as $id) {
            if (isset($allTypesPrestations[$id])) {
                $libelles[] = $allTypesPrestations[$id]->libelle;
            }
        }

        return $libelles;
    }

    /**
     * Récupérer les types de prestations complets
     */
    private function getTypesPrestationsComplets($typeDePrestationsJson, $allTypesPrestations)
    {
        if (empty($typeDePrestationsJson)) {
            return [];
        }

        $ids = json_decode($typeDePrestationsJson, true);
        if (!is_array($ids)) {
            return [];
        }

        $types = [];
        foreach ($ids as $id) {
            if (isset($allTypesPrestations[$id])) {
                $types[] = $this->attachTypeDePrestationMediaUrls(clone $allTypesPrestations[$id]);
            }
        }

        return $types;
    }

    private function attachArticleMediaUrls($article)
    {
        if (!$article) {
            return $article;
        }

        $article->image = !empty($article->image)
            ? $this->wasabiService->temporaryUrl($article->image)
            : null;

        return $article;
    }

    private function attachPromotionMediaUrls($promotion)
    {
        if (!$promotion) {
            return $promotion;
        }

        $promotion->image = !empty($promotion->image)
            ? $this->wasabiService->temporaryUrl($promotion->image)
            : null;

        if ($promotion->relationLoaded('etablissement') && $promotion->etablissement) {
            $promotion->setRelation('etablissement', $this->attachEtablissementMediaUrls($promotion->etablissement));
        }

        return $promotion;
    }

    private function attachCategorieServiceMediaUrls($categorieService)
    {
        if (!$categorieService) {
            return $categorieService;
        }

        $categorieService->image = !empty($categorieService->image)
            ? $this->wasabiService->temporaryUrl($categorieService->image)
            : null;

        return $categorieService;
    }

    private function attachTypeDePrestationMediaUrls($typeDePrestation)
    {
        if (!$typeDePrestation) {
            return $typeDePrestation;
        }

        $typeDePrestation->image = !empty($typeDePrestation->image)
            ? $this->wasabiService->temporaryUrl(
                Str::contains($typeDePrestation->image, '/')
                    ? $typeDePrestation->image
                    : 'images/type_de_prestation/' . ltrim($typeDePrestation->image, '/')
            )
            : null;

        return $typeDePrestation;
    }

    /**
     * Display a listing of the resource.
     */
    public function getTypeEtablissement()
    {
        // Récupérer les établissements triés par ID décroissant
        $type_etablissements = TypeEtablissement::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($type_etablissements->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun type d'établissement enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type d'établissements.",
            'type_etablissements' => $type_etablissements,
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function getEtablissementByType(Request $request)
	{
		// Validation des données d'entrée
		$validator = Validator::make($request->all(), [
			'type_etablissement_id' => 'required|exists:type_etablissements,id',
			'longitude' => 'nullable|numeric',
			'latitude' => 'nullable|numeric',
			'service_mobile' => 'nullable|in:0,1',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Les données fournies ne sont pas valides.',
				'errors' => $validator->errors(),
			], 422);
		}

		$latitude = $request->latitude;
		$longitude = $request->longitude;

		// Construire la requête de base
		$query = Etablissement::where('type_etablissement_id', $request->type_etablissement_id)
			->where('statut', 1);

		// Filtrer par service_mobile si fourni
		if ($request->has('service_mobile') && $request->service_mobile !== null) {
			$query->where('service_mobile', $request->service_mobile);
		}

		// Ajouter le calcul de distance seulement si longitude et latitude sont fournies
		if ($latitude !== null && $longitude !== null) {
			$query->select(
				'*',
				DB::raw("
					CASE
						WHEN longitude IS NULL OR latitude IS NULL THEN 0
						ELSE (
							6371 * acos(
								cos(radians($latitude)) *
								cos(radians(latitude)) *
								cos(radians(longitude) - radians($longitude)) +
								sin(radians($latitude)) *
								sin(radians(latitude))
							)
						)
					END AS distance
				")
			);
		} else {
			$query->select('*');
		}

		$query->with(['type_etablissement', 'pays', 'ville', 'commune']);

		// Trier par distance seulement si longitude et latitude sont fournies
		if ($latitude !== null && $longitude !== null) {
			$query->orderBy('distance', 'asc');
		} else {
			$query->orderBy('id', 'desc');
		}

		$etablissements = $query->get();

		// Vérifier si des établissements existent
		if ($etablissements->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'Aucun établissement trouvé avec ces critères.',
			], 404);
		}

		// Récupérer tous les types de prestations en une seule requête
		$allTypesPrestations = Type_de_prestation::all()->keyBy('id');

		// Ajouter les libellés des types de prestations à chaque établissement
		$etablissements->transform(function ($etablissement) use ($allTypesPrestations) {
			// Ajouter les libellés des types de prestations
			$etablissement->types_prestations_libelles = $this->getTypesPrestationsLibelles($etablissement->type_de_prestations, $allTypesPrestations);
			$etablissement->types_prestations_complets = $this->getTypesPrestationsComplets($etablissement->type_de_prestations, $allTypesPrestations);

			// Ajouter l'indicatif '+225' au champ mobile s'il existe
			if (!empty($etablissement->mobile)) {
				$etablissement->mobile = $etablissement->mobile;
			}

			return $this->attachEtablissementMediaUrls($etablissement);
		});

		// Déterminer le message selon si la distance est calculée
		$message = ($latitude !== null && $longitude !== null)
			? 'Liste des établissements triée par proximité.'
			: 'Liste des établissements.';

		// Retourner les établissements
		return response()->json([
			'success' => true,
			'message' => $message,
			'etablissements' => $etablissements,
		], 200);
	}


	    /**
     * Display a listing of the resource.
     */
    public function getTypeAlert()
    {
        // Récupérer les établissements triés par ID décroissant
        $type_alert = Type_alert::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($type_alert->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun type d'alert enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type d'alert.",
            'type_alert' => $type_alert,
        ], 200);
    }

	    /**
     * Display a listing of the resource.
     */
    public function getArticleForEtablissement()
    {
        // Récupérer les établissements triés par ID décroissant
        $articles = Article::orderBy('id', 'desc')
        ->with(['etablissement' => function ($query) {
            $query->select('id', 'name', 'logo', 'mobile', 'longitude', 'latitude', 'mobile', 'mobile_fix');
        }])
        ->get();


        // Vérifier si des établissements existent
        if ($articles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun article enregistré pour le moment.",
            ], 404);
        }

        $articles = $articles->map(function ($article) {
            $article = $this->attachArticleMediaUrls($article);

            if ($article->relationLoaded('etablissement') && $article->etablissement) {
                $article->setRelation('etablissement', $this->attachEtablissementMediaUrls($article->etablissement));
            }

            return $article->toArray();
        });

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type d'alert.",
            'articles' => $articles,
        ], 200);
    }

	    /**
     * Display a listing of the resource.
     */
    public function getCategorieService()
    {
        $user = Auth::user();

        if (!empty($user->gestionnaire_de_flotte_id)) {
            // Récupérer les établissements triés par ID décroissant
            $categorie_services = Categorie_service::where(['statut' => 1, 'is_pro' => 1])
			->whereIn('pro_or_usager', [1, 2])
            ->orderBy('id', 'asc')->get();
        }else {
            // Récupérer les établissements triés par ID décroissant
            $categorie_services = Categorie_service::where('statut', 1)
			->whereIn('pro_or_usager', [0, 2])
            ->orderBy('id', 'asc')->get();
        }


        // Vérifier si des établissements existent
        if ($categorie_services->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucune catégorie de service enregistré pour le moment.",
            ], 404);
        }

        $categorie_services->transform(function ($categorieService) {
            return $this->attachCategorieServiceMediaUrls($categorieService);
        });

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des catégories de services.",
            'categorie_services' => $categorie_services,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getTypeDePrestation(Request $request)
    {
        // Récupérer les établissements triés par ID décroissant
        $type_de_prestataire = Type_de_prestation::orderBy('id', 'asc')->get();

        // Vérifier si des établissements existent
        if ($type_de_prestataire->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun type de prestation enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des types de prestation.",
            'type_de_prestataire' => $type_de_prestataire,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getTypeDePiece()
    {
        // Récupérer les établissements triés par ID décroissant
        $type_de_piece = Type_de_piece::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($type_de_piece->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun type de pièce enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type de pièce.",
            'type_de_piece' => $type_de_piece,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getTypeDeDemande()
    {
        // Récupérer les établissements triés par ID décroissant
        $type_de_demande = Type_de_demande::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($type_de_demande->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun type de demande enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type de pièce.",
            'type_de_demande' => $type_de_demande,
        ], 200);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function getEtablissementById(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'etablissement_id' => 'required|exists:etablissements,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'établissement avec ses relations
        $etablissement = Etablissement::where('id', $request->etablissement_id)
            ->with(['type_etablissement', 'pays', 'ville', 'commune', 'articles'])
            ->first();

        // Vérifier si l'établissement est nul
        if (!$etablissement) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun établissement trouvé avec cet ID.',
            ], 404);
        }

        // Récupérer tous les types de prestations en une seule requête
        $allTypesPrestations = TypeDePrestation::all()->keyBy('id');

        // Ajouter les libellés des types de prestations à l'établissement
        $etablissement->types_prestations_libelles = $this->getTypesPrestationsLibelles($etablissement->type_de_prestations, $allTypesPrestations);
        $etablissement->types_prestations_complets = $this->getTypesPrestationsComplets($etablissement->type_de_prestations, $allTypesPrestations);

        // Ajouter les libellés des relations
        $etablissement->type_etablissement_libelle = $etablissement->type_etablissement ? $etablissement->type_etablissement->libelle : null;
        $etablissement->pays_libelle = $etablissement->pays ? $etablissement->pays->libelle : null;
        $etablissement->ville_libelle = $etablissement->ville ? $etablissement->ville->libelle : null;
        $etablissement->commune_libelle = $etablissement->commune ? $etablissement->commune->libelle : null;

        $etablissement = $this->attachEtablissementMediaUrls($etablissement);

        // Retourner les détails de l'établissement
        return response()->json([
            'success' => true,
            'message' => 'Détails de l\'établissement.',
            'etablissement' => $etablissement,
        ], 200);
    }

    /**
     * Récupérer les articles d'un établissement
     */
    public function getArticlesByEtablissement(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'etablissement_id' => 'required|exists:etablissements,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer les articles de l'établissement
        $articles = Article::where('etablissement_id', $request->etablissement_id)
            ->orderBy('id', 'desc')
            ->get();

        // Vérifier si des articles existent
        if ($articles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun article trouvé pour cet établissement.',
            ], 404);
        }

        // Retourner la liste des articles
        return response()->json([
            'success' => true,
            'message' => 'Liste des articles de l\'établissement.',
            'articles' => $articles,
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function getEtablissementByCategorieService(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'categorie_service_id' => 'required|exists:categorie_services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'établissement avec ses relations
        $etablissement = Etablissement::where('categorie_service_id', $request->categorie_service_id)
            ->where('statut', 1)
            ->with(['type_etablissement', 'pays', 'ville', 'commune'])
            ->get();

        // Vérifier si l'établissement est nul
        if (!$etablissement) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun établissement trouvé avec cet ID.',
            ], 404);
        }

        // Retourner les détails de l'établissement
        return response()->json([
            'success' => true,
            'message' => 'Liste des services de proximite.',
            'etablissements' => $etablissement,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
   	public function searchAll(Request $request)
	{
		$searchTerm = $request->input('query');

		// Fonction pour formater les résultats
		$formatResult = function ($items, $etiquette) {
			return collect($items)->map(function ($item) use ($etiquette) {
				return [
					'id' => $item->id,
					'libelle_name' => $item->libelle_name ?? $item->name,
					'logo' => $item->image_logo ?? null,
					'adresse' => $item->adresse ?? null,
					'name' => $item->name ?? null,
					'description' => $item->description ?? null,
					'adresse_map' => $item->adresse_map ?? null,
					'mobile' => $item->mobile ?? null,
					'cover' => $item->cover ?? null,
					'longitude' => $item->longitude ?? null,
					'latitude' => $item->latitude ?? null,
					'etiquette' => $etiquette,
				];
			});
		};

		// Recherche dans la table services
		$services = Service::where('libelle', 'like', '%' . $searchTerm . '%')
			->leftJoin('etablissements', 'services.etablissement_id', '=', 'etablissements.id')
			->select(
				'services.id',
				'services.libelle as libelle_name',
				'services.image as image_logo',
				'etablissements.adresse',
				'etablissements.name',
				'etablissements.description',
				'etablissements.adresse_map',
				'etablissements.mobile',
				'etablissements.cover',
				'etablissements.longitude',
				'etablissements.latitude'
			)
			->get();

		// Recherche dans la table articles
		$articles = Article::where('libelle', 'like', '%' . $searchTerm . '%')
			->leftJoin('etablissements', 'articles.etablissement_id', '=', 'etablissements.id')
			->select(
				'articles.id',
				'articles.libelle as libelle_name',
				'articles.image as image_logo',
				'etablissements.adresse',
                'etablissements.name',
				'etablissements.description',
				'etablissements.adresse_map',
				'etablissements.mobile',
				'etablissements.cover',
				'etablissements.longitude',
				'etablissements.latitude'
			)
			->get();

		// Recherche dans la table etablissements
		$etablissements = Etablissement::where('name', 'like', '%' . $searchTerm . '%')
			->select(
				'id',
				'name as libelle_name',
			    'name',
				'logo as image_logo',
			    'logo',
				'adresse',
				'adresse_map',
				'description',
				'mobile',
				'cover',
				'longitude',
				'latitude'
			)
			->get();

		// Formatage des résultats
		$formattedServices = $formatResult($services, 'service');
		$formattedArticles = $formatResult($articles, 'article');
		$formattedEtablissements = $formatResult($etablissements, 'etablissement');

		// Fusionner les résultats en collections
		$results = collect()->merge($formattedServices)
			->merge($formattedArticles)
			->merge($formattedEtablissements);

		return response()->json($results);
	}


    public function searchArticleByEtablissement(Request $request, $id)
    {
        $searchTerm = $request->input('query');

        // Fonction pour formater les résultats
        $formatResult = function ($items, $etiquette) {
            return collect($items)->map(function ($item) use ($etiquette) {
                return [
                    'id' => $item->id,
                    'libelle_name' => $item->libelle_name ?? $item->name,
                    'image_logo' => $item->image_logo ?? null,
                    'description' => $item->description ?? null,
                    'amount' => $item->amount ?? null,
                    'etiquette' => $etiquette,
                ];
            });
        };

        // Recherche dans la table articles
        $articles = Article::where('libelle', 'like', '%' . $searchTerm . '%')
            ->where('etablissement_id', $id)
            ->select(
                'articles.id',
                'articles.libelle as libelle_name',
                'articles.image as image_logo',
                'articles.description',
                'articles.amount'
            )
            ->get();

        // Formatage des résultats
        $formattedArticles = $formatResult($articles, 'article');

        // Pas besoin de fusionner, car il n'y a qu'une seule collection
        return response()->json($formattedArticles);
    }

    public function searchServiceByEtablissement(Request $request, $id)
    {
        $searchTerm = $request->input('query');

        // Fonction pour formater les résultats
        $formatResult = function ($items, $etiquette) {
            return collect($items)->map(function ($item) use ($etiquette) {
                return [
                    'id' => $item->id,
                    'libelle_name' => $item->libelle_name ?? $item->name,
                    'image_logo' => $item->image_logo ?? null,
                    'description' => $item->description ?? null,
                    'amount_min' => $item->amount_min ?? null,
                    'etiquette' => $etiquette,
                ];
            });
        };

        // Recherche dans la table services
        $services = Service::where('libelle', 'like', '%' . $searchTerm . '%')
            ->where('etablissement_id', $id)
            ->select(
                'services.id',
                'services.libelle as libelle_name',
                'services.image as image_logo',
                'services.description',
                'services.amount_min'
            )
            ->get();

        // Formatage des résultats
        $formattedServices = $formatResult($services, 'service');

        // Pas besoin de fusionner, car il n'y a qu'une seule collection
        return response()->json($formattedServices);
    }


    /**
     * Display a listing of the resource.
     */
    public function getPromotionOfEtablissement()
    {
        // Récupérer la date actuelle (au format Y-m-d)
        $currentDate = Carbon::now()->toDateString(); // Format : 'YYYY-MM-DD'

        // Récupérer les promotions dont la date de fin n'est pas expirée et le statut est 1
        $promotions = Promotion::where('statut', 1)
                               ->whereDate('date_fin', '>=', $currentDate) // Utilisation de whereDate pour comparer les dates sans l'heure
                               ->orderBy('id', 'desc')
                               ->with('etablissement')
                               ->get();

        // Vérifier si des promotions existent
        if ($promotions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucune promotion active pour le moment.",
            ], 404);
        }

        $promotions = $promotions->map(function ($promotion) {
            return $this->attachPromotionMediaUrls($promotion);
        });

        // Retourner la liste des promotions
        return response()->json([
            'success' => true,
            'message' => "Liste des promotions.",
            'promotions' => $promotions,
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function getPromotionByEtablissement($id)
	{
		// Récupérer la date actuelle (au format Y-m-d)
		$currentDate = Carbon::now()->toDateString(); // Format : 'YYYY-MM-DD'

		// Récupérer la première promotion dont la date de fin n'est pas expirée et le statut est 1
		$promotion = Promotion::where(['statut' => 1, 'etablissement_id' => $id])
			->whereDate('date_fin', '>=', $currentDate) // Utilisation de whereDate pour comparer les dates sans l'heure
			->orderBy('id', 'desc')
			->with('etablissement')
			->first();

		// Vérifier si une promotion existe
		if (!$promotion) {
			return response()->json([
				'success' => false,
				'message' => "Aucune promotion active pour le moment.",
			], 404);
		}

		$promotion = $this->attachPromotionMediaUrls($promotion);

		// Retourner la promotion
		return response()->json([
			'success' => true,
			'message' => "Promotion trouvée.",
			'promotion' => $promotion,
		], 200);
	}


    /**
     * Display a listing of the resource.
     */
    public function getAllCommissariatAgentConstat(Request $request)
	{
		// Validation des données d'entrée
		$validator = Validator::make($request->all(), [
			'longitude' => 'required|numeric',
			'latitude' => 'required|numeric',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Les données fournies ne sont pas valides.',
				'errors' => $validator->errors(),
			], 422);
		}

		$latitude = $request->latitude;
		$longitude = $request->longitude;

		// Requête pour récupérer les commissariats triés par distance
		$commissariats = Commissariat::select(
			'*', // Remplace '*' par les champs spécifiques si nécessaire
			DB::raw("
                CASE
                    WHEN longitude IS NULL OR latitude IS NULL THEN 0
                    ELSE (
                        6371 * acos(
                            cos(radians($latitude)) *
                            cos(radians(latitude)) *
                            cos(radians(longitude) - radians($longitude)) +
                            sin(radians($latitude)) *
                            sin(radians(latitude))
                        )
                    )
                END AS distance
            ")
		)
		->orderBy('distance', 'asc') // Trier par distance croissante
		->get();

		// Vérifier si des commissariats existent
		if ($commissariats->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => "Aucun commissariat trouvé.",
			], 404);
		}

		// Retourner la liste des commissariats triés par proximité
		return response()->json([
			'success' => true,
			'message' => "Liste des commissariats triée par proximité.",
			'commissariats' => $commissariats,
		], 200);
	}



    /**
     * Display a listing of the resource.
     */
	public function getAllStationServiceNormal(Request $request)
	{
		// Validation des données d'entrée
		$validator = Validator::make($request->all(), [
			'longitude' => 'required|numeric',
			'latitude' => 'required|numeric',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Les données fournies ne sont pas valides.',
				'errors' => $validator->errors(),
			], 422);
		}

		$latitude = $request->latitude;
		$longitude = $request->longitude;

		// Requête pour récupérer les commissariats triés par distance
		$station_service = Station_service::select(
			'*', // Remplace '*' par les champs spécifiques si nécessaire
			DB::raw("
                CASE
                    WHEN longitude IS NULL OR latitude IS NULL THEN 0
                    ELSE (
                        6371 * acos(
                            cos(radians($latitude)) *
                            cos(radians(latitude)) *
                            cos(radians(longitude) - radians($longitude)) +
                            sin(radians($latitude)) *
                            sin(radians(latitude))
                        )
                    )
                END AS distance
            ")
		)
		->where('statut', 1)
		->where('borne_electrique', 0)
        ->with('ville', 'commune')
		->orderBy('distance', 'asc') // Trier par distance croissante
		->get();

		// Vérifier si des commissariats existent
		if ($station_service->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => "Aucun station service trouvé.",
			], 404);
		}

		// Retourner la liste des commissariats triés par proximité
		return response()->json([
			'success' => true,
			'message' => "Liste des stations services triée par proximité.",
			'station_services' => $station_service,
		], 200);
	}

    /**
     * Display a listing of the resource.
     */
    public function getAllStationServiceElectrique(Request $request)
	{
		// Validation des données d'entrée
		$validator = Validator::make($request->all(), [
			'longitude' => 'required|numeric',
			'latitude' => 'required|numeric',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Les données fournies ne sont pas valides.',
				'errors' => $validator->errors(),
			], 422);
		}

		$latitude = $request->latitude;
		$longitude = $request->longitude;

		// Requête pour récupérer les commissariats triés par distance
		$station_service = Station_service::select(
			'*', // Remplace '*' par les champs spécifiques si nécessaire
			DB::raw("
                CASE
                    WHEN longitude IS NULL OR latitude IS NULL THEN 0
                    ELSE (
                        6371 * acos(
                            cos(radians($latitude)) *
                            cos(radians(latitude)) *
                            cos(radians(longitude) - radians($longitude)) +
                            sin(radians($latitude)) *
                            sin(radians(latitude))
                        )
                    )
                END AS distance
            ")
		)
		->where('statut', 1)
		->where('borne_electrique', 1)
        ->with('ville', 'commune')
		->orderBy('distance', 'asc') // Trier par distance croissante
		->get();

		// Vérifier si des commissariats existent
		if ($station_service->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => "Aucun station service trouvé.",
			], 404);
		}

		// Retourner la liste des commissariats triés par proximité
		return response()->json([
			'success' => true,
			'message' => "Liste des stations services triée par proximité.",
			'station_services' => $station_service,
		], 200);
	}

    /**
     * Display a listing of the resource.
     */
    public function getAllSapeurPompier(Request $request)
	{
		// Validation des données d'entrée
		$validator = Validator::make($request->all(), [
			'longitude' => 'required|numeric',
			'latitude' => 'required|numeric',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Les données fournies ne sont pas valides.',
				'errors' => $validator->errors(),
			], 422);
		}

		$latitude = $request->latitude;
		$longitude = $request->longitude;

		// Requête pour récupérer les commissariats triés par distance
		$sapeur_pompier = Sapeur_pompier::select(
			'*', // Remplace '*' par les champs spécifiques si nécessaire
			DB::raw("
				CASE
					WHEN longitude IS NULL OR latitude IS NULL THEN 0
					ELSE (
						6371 * acos(
							cos(radians($latitude)) *
							cos(radians(latitude)) *
							cos(radians(longitude) - radians($longitude)) +
							sin(radians($latitude)) *
							sin(radians(latitude))
						)
					)
				END AS distance
			")
		)
		->orderBy('distance', 'asc')
		->get();


		// Vérifier si des commissariats existent
		if ($sapeur_pompier->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => "Aucun sapeur pompier trouvé.",
			], 404);
		}

		// Retourner la liste des commissariats triés par proximité
		return response()->json([
			'success' => true,
			'message' => "Liste des sapeur pompier triée par proximité.",
			'sapeur_pompiers' => $sapeur_pompier,
		], 200);
	}

    /**
     * Display a listing of the resource.
     */
    public function getAgentConstatByCommune(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'commune_id' => 'required|exists:communes,id',
        ]);

        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $commissariats = Commissariat::where('commune_id', $request->commune_id)
        ->orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($commissariats->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune agent constat enregistré pour cette commune.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des agents constats.',
            'commissariats' => $commissariats,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getSapeurPompierByCommune(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'commune_id' => 'required|exists:communes,id',
        ]);

        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $sapeur_pompier = Sapeur_pompier::where('commune_id', $request->commune_id)
        ->orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($sapeur_pompier->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun service de sapeur pompier enregistré pour cette commune.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des sapeurs-pompiers.',
            'sapeur_pompier' => $sapeur_pompier,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getCommuneAll(Request $request)
    {
        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $commune = Commune::orderBy('id', 'desc')
        ->with('ville')
        ->get();

        // Vérifier si des établissements existent
        if ($commune->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun commune enregistré.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des communes.',
            'commune' => $commune,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getCabinetExpertise(Request $request)
    {
        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $cabinet_expertises = Cabinet_expertise::orderBy('id', 'desc')
        ->with('ville', 'commune')
        ->get();

        // Vérifier si des établissements existent
        if ($cabinet_expertises->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun Cabinet expertise enregistré.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des communes.',
            'cabinet_expertises' => $cabinet_expertises,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getEtablissementByTypeDePrestation(Request $request)
    {
        // Vérifier si l'utilisateur est authentifié
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'type_de_prestation_id' => 'required|exists:type_de_prestations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer les établissements avec des données JSON valides
        $etablissements = Etablissement::whereNotNull('type_de_prestations')
            ->whereRaw('JSON_VALID(type_de_prestations)')
            ->whereJsonContains('type_de_prestations', $request->type_de_prestation_id)
            ->orderBy('id', 'desc')
            ->get();

        // Vérifier si des établissements existent
        if ($etablissements->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun établissement enregistré pour ce type de prestation.',
            ], 404);
        }

        // Récupérer tous les types de prestations en une seule requête
        $allTypesPrestations = TypeDePrestation::all()->keyBy('id');

        // Ajouter les libellés des types de prestations à chaque établissement
        $etablissements = $etablissements->map(function ($etablissement) use ($allTypesPrestations) {
            $etablissement->types_prestations_libelles = $this->getTypesPrestationsLibelles($etablissement->type_de_prestations, $allTypesPrestations);
            $etablissement->types_prestations_complets = $this->getTypesPrestationsComplets($etablissement->type_de_prestations, $allTypesPrestations);
            $etablissement = $this->attachEtablissementMediaUrls($etablissement);

            return $etablissement->toArray();
        });

        // Récupérer le libelle du type de prestation recherché
        $typeDePrestation = TypeDePrestation::select('id', 'libelle')
            ->where('id', $request->type_de_prestation_id)
            ->first();

        // Retourner la liste des établissements avec le libelle
        return response()->json([
            'success' => true,
            'message' => 'Liste des établissements trouvés.',
            'type_de_prestation_libelle' => $typeDePrestation ? $typeDePrestation->libelle : null,
            'etablissement_by_type_de_prestation' => $etablissements,
        ], 200);
    }

	/**
     * Crée un nouvel share.
     */
    public function shareLocalisation(Request $request)
    {
        $validated = $request->validate([
            'longitude' => 'required|string',
            'latitude' => 'required|string',
            'usager_id' => 'required|integer',
            'etablissement_id' => 'required|integer',
        ]);

        $share = new shareLocalisation();
		$share->longitude = $request->longitude;
		$share->latitude = $request->latitude;
		$share->usager_id = $request->usager_id;
		$share->etablissement_id = $request->etablissement_id;
		
		$share->save();

        return response()->json($share, 201);
    }

}
