<?php

namespace App\Http\Controllers;

use App\Models\Autodoc;
use App\Models\Type_docauto;
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

class AutodocController extends Controller
{
    protected $wasabiService;

    public function __construct(WasabiService $wasabiService)
    {
        $this->wasabiService = $wasabiService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
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
        $autodocs = Autodoc::where('user_id', $user->id)
        ->orderBy('id', 'desc')
        ->with('vehicule', 'type_docauto')
        ->get();

        // Vérifier si des établissements existent
        if ($autodocs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun document enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des documents.",
            'autodocs' => $autodocs->map(function ($autodoc) {
                return $this->attachAutodocRelations($autodoc);
            }),
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexByFlotte(Request $request)
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les documents triés par ID décroissant
        $autodocs = Autodoc::where([
            'gestionnaire_de_flotte_id' => $user->gestionnaire_de_flotte_id,
            'provenance' => 'flotte',
            'user_id' => $user->id
        ])
        ->orderBy('id', 'desc')
        ->with('vehicule', 'type_docauto')
        ->get();

        // Vérifier si des documents existent
        if ($autodocs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun document enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des documents
        return response()->json([
            'success' => true,
            'message' => "Liste des documents.",
            'autodocs' => $autodocs->map(function ($autodoc) {
                return $this->attachAutodocRelations($autodoc);
            }),
        ], 200);
    }

	    /**
     * Display a listing of the resource.
     */
    public function getTypeDocauto(Request $request)
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
        $type_docautos = Type_docauto::all();

        // Vérifier si des établissements existent
        if ($type_docautos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun document enregistré pour le moment.",
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => "Liste des documents autos.",
            'type_docautos' => $type_docautos,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'images' => 'nullable|array|max:4', // Autoriser jusqu'à 4 images
                'images.*' => 'file|image|max:12048', // Taille max de 12MB
                'vehicule_id' => 'nullable|exists:vehicules,id',
                'type_docauto_id' => 'required|exists:type_docautos,id',
            ], [
                'images.max' => 'Vous pouvez télécharger jusqu\'à 4 images.',
                'images.*.image' => 'Chaque fichier doit être une image valide.',
                'images.*.max' => 'La taille maximale pour chaque image est de 12 MB.',
                'vehicule_id.exists' => 'Le véhicule sélectionné est invalide.',
                'type_docauto_id.exists' => 'Le type de pièce sélectionné est invalide.',
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
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable.',
                ], 404);
            }

            // Création d'une nouvelle déclaration
            $autodoc = new Autodoc();

            $autodoc->type_docauto_id = $request->type_docauto_id;
            $autodoc->vehicule_id = $request->vehicule_id; // Correctement assigné
            $autodoc->user_id = $user->id;

            if(!empty($user->gestionnaire_de_flotte_id)){
                $autodoc->gestionnaire_de_flotte_id = $user->gestionnaire_de_flotte_id;
                $autodoc->provenance = 'flotte';
            }

            // Gestion des images
            if ($request->hasFile('images')) {
                $imagesPaths = [];
                foreach ($request->file('images') as $photo) {
                    try {
                        $imagesPaths[] = $this->wasabiService->uploadFile(
                            $photo,
                            'autodoc/images',
                            'autodoc'
                        );
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Erreur lors du téléchargement des images.',
                            'error' => $e->getMessage(),
                        ], 500);
                    }
                }
                $autodoc->images = $imagesPaths;
            }

            $autodoc->save();

            return response()->json([
                'success' => true,
                'message' => 'Document enregistré avec succès.',
                'autodoc' => $this->attachAutodocImageUrls($autodoc),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function update(Request $request, $id)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'images' => 'nullable|array|max:4', // Autoriser jusqu'à 4 images
                'images.*' => 'file|image|max:12048', // Taille max de 12MB
                'vehicule_id' => 'nullable|exists:vehicules,id',
                'type_docauto_id' => 'required|exists:type_docautos,id',
            ], [
                'images.max' => 'Vous pouvez télécharger jusqu\'à 4 images.',
                'images.*.image' => 'Chaque fichier doit être une image valide.',
                'images.*.max' => 'La taille maximale pour chaque image est de 12 MB.',
                'vehicule_id.exists' => 'Le véhicule sélectionné est invalide.',
                'type_docauto_id.exists' => 'Le type de pièce sélectionné est invalide.',
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
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable.',
                ], 404);
            }

            // Récupérer l'objet Autodoc existant par son ID
            $autodoc = Autodoc::find($id);
            if (!$autodoc) {
                return response()->json([
                    'success' => false,
                    'message' => 'Déclaration non trouvée.',
                ], 404);
            }

            // Vérification que l'utilisateur connecté est bien celui qui a créé la déclaration
            if ($autodoc->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas mettre à jour cette déclaration.',
                ], 403);
            }

            // Mise à jour des champs
            $autodoc->vehicule_id = $request->vehicule_id;
            $autodoc->type_docauto_id = $request->type_docauto_id;
            if(!empty($user->gestionnaire_de_flotte_id)){
                $autodoc->gestionnaire_de_flotte_id = $user->gestionnaire_de_flotte_id;
                $autodoc->provenance = 'flotte';
            }

            if ($request->hasFile('images')) {
                // Supprimer les anciennes images
                if ($autodoc->images) {
                    foreach ((array) $autodoc->images as $photo) {
                        if (!empty($photo)) {
                            $this->wasabiService->deleteFile($photo);
                        }
                    }
                }

                // Sauvegarder les nouvelles images
                $imagesPaths = [];
                foreach ($request->file('images') as $photo) {
                    $imagesPaths[] = $this->wasabiService->uploadFile(
                        $photo,
                        'autodoc/images',
                        'autodoc'
                    );
                }

                // Sauvegarder les chemins dans la base de données
                $autodoc->images = $imagesPaths;
            }

            // Sauvegarde des modifications
            $autodoc->save();

            return response()->json([
                'success' => true,
                'message' => 'Document mis à jour avec succès.',
                'autodoc' => $this->attachAutodocImageUrls($autodoc),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
/**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'autodoc_id' => 'required|exists:autodocs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $id = $request->autodoc_id;

        // Récupérer le véhicule
        $autodoc = Autodoc::find($id);
        if (!$autodoc) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Supprimer les images associées
            if ($autodoc->images) {
                foreach ((array) $autodoc->images as $photo) {
                    if (!empty($photo)) {
                        $this->wasabiService->deleteFile($photo);
                    }
                }
            }

            // Supprimer le véhicule
            $autodoc->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Véhicule supprimé avec succès.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la suppression du véhicule.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    protected function attachAutodocImageUrls($autodoc)
    {
        if (!$autodoc || empty($autodoc->images)) {
            return $autodoc;
        }

        $images = is_array($autodoc->images)
            ? $autodoc->images
            : json_decode($autodoc->images, true);

        if (!is_array($images)) {
            return $autodoc;
        }

        $autodoc->images = array_map(function ($image) {
            try {
                return $this->wasabiService->temporaryUrl($image) ?? $image;
            } catch (\Throwable $e) {
                return $image;
            }
        }, $images);

        return $autodoc;
    }

    protected function attachVehiculePhotoUrls($vehicule)
    {
        if (!$vehicule || empty($vehicule->photos)) {
            return $vehicule;
        }

        $photos = is_array($vehicule->photos)
            ? $vehicule->photos
            : json_decode($vehicule->photos, true);

        if (!is_array($photos)) {
            return $vehicule;
        }

        $vehicule->photos = array_map(function ($photo) {
            try {
                return $this->wasabiService->temporaryUrl($photo) ?? $photo;
            } catch (\Throwable $e) {
                return $photo;
            }
        }, $photos);

        return $vehicule;
    }

    protected function attachAutodocRelations($autodoc)
    {
        $autodoc = $this->attachAutodocImageUrls($autodoc);

        if ($autodoc && $autodoc->vehicule) {
            $autodoc->vehicule = $this->attachVehiculePhotoUrls($autodoc->vehicule);
        }

        return $autodoc;
    }
}
