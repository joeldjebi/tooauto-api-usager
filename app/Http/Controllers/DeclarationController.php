<?php

namespace App\Http\Controllers;

use App\Models\Declaration;
use App\Models\Mauvais_stationnement;
use App\Models\Vehicule;
use App\Models\Type_de_declaration;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Commune;
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

class DeclarationController extends Controller
{
    protected $wasabiService;

    public function __construct(WasabiService $wasabiService)
    {
        $this->wasabiService = $wasabiService;
    }

    /**
     * Display a listing of the resource.
     */
    public function indexTypeDeDeclaration(Request $request)
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $type_de_declaration = Type_de_declaration::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($type_de_declaration->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun type de déclaration enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type d'alert.",
            'type_de_declaration' => $type_de_declaration,
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function getDeclarationDePerte(Request $request)
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $declaration = Declaration::where(['usager_id' => $user->id, 'statut' => 1])
        ->where('type_de_declaration_id', 1)
        ->orderBy('id', 'desc')
        ->get();

        // Vérifier si des établissements existent
        if ($declaration->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucune déclaration enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type d'alert.",
            'declaration' => $declaration->map(function ($item) {
                return $this->attachDeclarationImageUrls($item);
            }),
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function getDeclarationDeStationnement(Request $request)
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $declaration = Declaration::where(['usager_id' => $user->id, 'statut' => 1])
        ->where('type_de_declaration_id', 2)
        ->orderBy('id', 'desc')
        ->get();

        // Vérifier si des établissements existent
        if ($declaration->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucune déclaration enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des type d'alert.",
            'declaration' => $declaration->map(function ($item) {
                return $this->attachDeclarationImageUrls($item);
            }),
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function storeDeclaration(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'images' => 'nullable|array|size:4',
            'images.*' => 'file|image|max:12048',
            'description' => 'required|string',
            'date' => 'required|date',
            'lieu' => 'required|string',
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'matricule' => 'nullable|string',
            'commune_id' => 'required|exists:communes,id',
            'type_de_declaration_id' => 'required|exists:type_de_declarations,id',
            'vehicule_id' => 'required|exists:vehicules,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Création d'une nouvelle déclaration
        $declaration = new Declaration();
        $declaration->description = $request->description;
        $declaration->date = $request->date;
        $declaration->lieu = $request->lieu;
        $declaration->longitude = $request->longitude;
        $declaration->latitude = $request->latitude;
        $declaration->matricule = $request->matricule;
        $declaration->commune_id = $request->commune_id;
        $declaration->type_de_declaration_id = $request->type_de_declaration_id;
        $declaration->vehicule_id = $request->vehicule_id;
        $declaration->usager_id = $user->id;

        // Gestion des images
        if ($request->hasFile('images')) {
            $imagesPaths = [];
            foreach ($request->file('images') as $photo) {
                try {
                    $imagesPaths[] = $this->wasabiService->uploadFile(
                        $photo,
                        'declaration/images',
                        'declaration'
                    );
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erreur lors du téléchargement des images.',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }
            $declaration->images = $imagesPaths;
        }

        if ($declaration->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Déclaration enregistrée avec succès.',
                'declaration' => $this->attachDeclarationImageUrls($declaration),
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la déclaration.',
            ], 500);
        }
    }

    /**
    * Update the specified resource in storage.
    */
    public function updateDeclaration(Request $request, $id)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'images' => 'nullable|array|size:4',
            'images.*' => 'file|image|max:12048',
            'description' => 'required|string',
            'date' => 'required|date',
            'lieu' => 'required|string',
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'matricule' => 'nullable|string',
            'commune_id' => 'required|exists:communes,id',
            'type_de_declaration_id' => 'required|exists:type_de_declarations,id',
            'vehicule_id' => 'required|exists:vehicules,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer la déclaration existante
        $declaration = Declaration::where(['id' => $id, 'usager_id' => $user->id])->first();

        if (!$declaration) {
            return response()->json([
                'success' => false,
                'message' => 'Déclaration introuvable.',
            ], 404);
        }

        // Mise à jour des champs
        $declaration->description = $request->description;
        $declaration->date = $request->date;
        $declaration->lieu = $request->lieu;
        $declaration->longitude = $request->longitude;
        $declaration->latitude = $request->latitude;
        $declaration->matricule = $request->matricule;
        $declaration->commune_id = $request->commune_id;
        $declaration->type_de_declaration_id = $request->type_de_declaration_id;
        $declaration->vehicule_id = $request->vehicule_id;

        // Gestion des images
        if ($request->hasFile('images')) {
            $imagesPaths = [];

            // Supprimer les anciennes images, si nécessaire
            if (!empty($declaration->images)) {
                foreach ((array) $declaration->images as $oldImage) {
                    if (!empty($oldImage)) {
                        $this->wasabiService->deleteFile($oldImage);
                    }
                }
            }

            foreach ($request->file('images') as $photo) {
                try {
                    $imagesPaths[] = $this->wasabiService->uploadFile(
                        $photo,
                        'declaration/images',
                        'declaration'
                    );
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erreur lors du téléchargement des images.',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }
            $declaration->images = $imagesPaths;
        }

        if ($declaration->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Déclaration mise à jour avec succès.',
                'declaration' => $this->attachDeclarationImageUrls($declaration),
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la déclaration.',
            ], 500);
        }
    }

    protected function attachDeclarationImageUrls($declaration)
    {
        if (!$declaration || empty($declaration->images)) {
            return $declaration;
        }

        $images = is_array($declaration->images)
            ? $declaration->images
            : json_decode($declaration->images, true);

        if (!is_array($images)) {
            return $declaration;
        }

        $imageUrls = array_values(array_filter(array_map(function ($image) {
            return $this->declarationImageUrl($image);
        }, $images)));

        $declaration->images = $imageUrls;
        $declaration->image_urls = $imageUrls;

        return $declaration;
    }

    protected function declarationImageUrl(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $path = $this->normalizeDeclarationImagePath($image);

        try {
            return $this->wasabiService->temporaryUrl($path) ?? $this->wasabiPublicUrl($path);
        } catch (\Throwable $e) {
            if (Str::contains($image, '/')) {
                return $this->wasabiPublicUrl($path);
            }

            return asset('declaration/images/' . ltrim($image, '/'));
        }
    }

    protected function normalizeDeclarationImagePath(string $image): string
    {
        if (Str::contains($image, '/')) {
            return ltrim($image, '/');
        }

        return 'declaration/images/' . ltrim($image, '/');
    }

    protected function wasabiPublicUrl(string $path): string
    {
        return rtrim((string) config('wasabi.url'), '/') . '/' . ltrim($path, '/');
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function deleteDeclaration(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'declaration_id' => 'required|exists:declarations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer la déclaration existante
        $declaration = Declaration::where([
            'id' => $request->declaration_id,
            'usager_id' => $user->id, // Assurez-vous que la colonne est bien "usager_id" (ou modifiez si c'est différent)
        ])->first();

        if (!$declaration) {
            return response()->json([
                'success' => false,
                'message' => 'Déclaration introuvable ou non autorisée.',
            ], 404);
        }

        if (!empty($declaration->images)) {
            foreach ((array) $declaration->images as $image) {
                if (!empty($image)) {
                    $this->wasabiService->deleteFile($image);
                }
            }
        }

        if ($declaration->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Déclaration supprimée avec succès.',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la déclaration.',
            ], 500);
        }
    }


    /**
     * Display a listing of the resource.
     */
    public function getMauvaisStationnement(Request $request)
	{
		// Récupérer l'utilisateur connecté
		$user = auth()->user();

		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => 'Utilisateur introuvable',
			], 404);
		}

		// Récupérer les mauvais stationnements de l'utilisateur
		$mauvaisStationnements = Mauvais_stationnement::where('user_id', $user->id)
			->where('type', 1)
			->with('vehicule', 'user')
			->orderBy('id', 'desc')
			->get();

		// Vérifier s'il y a des résultats
		if ($mauvaisStationnements->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => "Aucun mauvais stationnement enregistré pour le moment.",
			], 404);
		}

		// Retourner les résultats
		return response()->json([
			'success' => true,
			'message' => "Liste des mauvais stationnements.",
			'declaration' => $mauvaisStationnements,
		], 200);
	}


	/**
     * Display a listing of the resource.
     */
    public function getVehiculeAbandonne()
	{
		// Récupérer l'utilisateur connecté
		$user = auth()->user();

		if (empty($user)) {
			return response()->json([
				'success' => false,
				'message' => 'Utilisateur introuvable',
			], 404);
		}

		// Récupérer les véhicules abandonnés
		$vehiculeAbandonnes = Mauvais_stationnement::where('user_id', $user->id)
			->where('type', 2)
			->with('vehicule', 'user')
			->orderBy('id', 'desc')
			->get();

		if ($vehiculeAbandonnes->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => "Aucun véhicule abandonné enregistré pour le moment.",
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => "Liste des véhicules abandonnés.",
			'vehicule_abandonne' => $vehiculeAbandonnes,
		], 200);
	}


    /**
     * Show the form for creating a new resource.
     */
    public function storeMauvaisStationnement(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'immatriculation' => 'required|string',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

		$vehicule = Vehicule::where('matricule', $request->immatriculation)->first();
		if (empty($vehicule)) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicule introuvable dans le systeme',
            ], 404);
        }

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Création d'une nouvelle déclaration
        $mauvais_stationnements = new Mauvais_stationnement();
        $mauvais_stationnements->longitude = $request->longitude;
        $mauvais_stationnements->latitude = $request->latitude;
        $mauvais_stationnements->immatriculation = $request->immatriculation;
        $mauvais_stationnements->vehicule_id = $vehicule->id;
        $mauvais_stationnements->user_id = $user->id;
        $mauvais_stationnements->type = $request->type;

        if ($mauvais_stationnements->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Mauvais stationnement enregistrée avec succès.',
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du mauvais stationnement.',
            ], 500);
        }
    }

}
