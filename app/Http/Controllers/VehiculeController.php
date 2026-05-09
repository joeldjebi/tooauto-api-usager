<?php

namespace App\Http\Controllers;

use App\Models\Vehicule;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Alert;
use App\Models\Type_de_vehicule;
use App\Models\Type_de_carburant;
use App\Models\Vehicule_concessionnaire;
use App\Models\Concessionnaire;
use App\Models\Marque;
use App\Models\AbonnementUsager;
use App\Models\VehiculeDeletion;
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

class VehiculeController extends Controller
{
    protected $wasabiService;

    public function __construct(WasabiService $wasabiService)
    {
        $this->wasabiService = $wasabiService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
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
        $vehicules = Vehicule::where('user_id', $user->id)
		->with('marque')
        ->orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($vehicules->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun vehicule enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des vehicules.',
            'vehicules' => $vehicules->map(function ($vehicule) {
                return $this->attachVehiculePhotoUrls($vehicule);
            }),
        ], 200);
    }

	
    /**
     * Display a listing of the resource.
     */
    public function indexTypeDeVehicule()
    {
        $user = auth()->user();
        
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }
    
        $type_de_vehicule = Type_de_vehicule::orderBy('id', 'desc')->get();
    
        if ($type_de_vehicule->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun type de vehicule enregistré pour le moment.',
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Liste des types de vehicules.',
            'type_de_vehicules' => $type_de_vehicule,
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexTypeDeCarburant()
    {
        $user = auth()->user();
        
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }
    
        $type_de_carburant = Type_de_carburant::orderBy('id', 'desc')->get();
    
        if ($type_de_carburant->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun type de carburant enregistré pour le moment.',
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Liste des types de carburant.',
            'type_de_carburants' => $type_de_carburant,
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexMarque()
    {
        $user = auth()->user();
        
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }
    
        $marques = Marque::orderBy('id', 'desc')->get();
    
        if ($marques->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune marque enregistré pour le moment.',
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Liste des marque.',
            'marques' => $marques,
        ], 200);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'matricule' => 'required|string|unique:vehicules',
            'carte_grise' => 'required|string|unique:vehicules',
            'photos' => 'required|array|size:4', // Vérifie que 4 fichiers sont fournis
            'photos.*' => 'file|image|max:25048', // Chaque fichier doit être une image de max 2 MB
            'type_de_vehicule_id' => 'required|exists:type_de_vehicules,id',
            'marque_id' => 'required|exists:marques,id',
            'type_de_carburant_id' => 'required|exists:type_de_carburants,id',
            'couleur' => 'required|string|max:50',
            'modele' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        $quotaCheck = $this->checkVehicleQuota($user);
        if (!$quotaCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $quotaCheck['message'],
                'quota' => $quotaCheck['quota'],
            ], $quotaCheck['status']);
        }
    
        DB::beginTransaction();
        try {
            // Création du véhicule
            $vehicule = new Vehicule();
            $vehicule->matricule = $request->matricule;
            $vehicule->carte_grise = $request->carte_grise;
            $vehicule->type_de_vehicule_id = $request->type_de_vehicule_id;
            $vehicule->marque_id = $request->marque_id;
            $vehicule->type_de_carburant_id = $request->type_de_carburant_id;
            $vehicule->couleur = $request->couleur;
            $vehicule->modele = $request->modele;
            $vehicule->user_id = $user->id;
            
            if(!empty($user->gestionnaire_de_flotte_id)){
                $vehicule->provenance = 'flotte';
                $vehicule->provenance_by = NULL;
                $vehicule->gestionnaire_de_flotte_id = $user->gestionnaire_de_flotte_id;
            }
    
            // Sauvegarde des photos
            if ($request->hasFile('photos')) {
                $photosPaths = [];
            
                foreach ($request->file('photos') as $photo) {
                    $photosPaths[] = $this->wasabiService->uploadFile(
                        $photo,
                        'images/vehicule',
                        'vehicule'
                    );
                }
            
                // Liaison des chemins des photos avec le véhicule (stocké en JSON)
                $vehicule->photos = json_encode($photosPaths);
            }
            
            $vehicule->save();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Véhicule enregistré avec succès.',
                'vehicule' => $this->attachVehiculePhotoUrls($vehicule),
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement du véhicule.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function updateVehicule(Request $request, $id)
    {
        $vehicule = Vehicule::find($id);
    
        if (!$vehicule) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
            ], 404);
        }
        
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'matricule' => 'sometimes|string|unique:vehicules,matricule,' . $id,
            'carte_grise' => 'sometimes|string|unique:vehicules,carte_grise,' . $id,
            'photos' => 'sometimes|array|size:4', // Facultatif mais doit contenir exactement 4 fichiers si présent
            'photos.*' => 'file|image|max:2048',
            'type_de_vehicule_id' => 'sometimes|exists:type_de_vehicules,id',
            'marque_id' => 'sometimes|exists:marques,id',
            'type_de_carburant_id' => 'sometimes|exists:type_de_carburants,id',
            'couleur' => 'sometimes|string|max:50',
            'modele' => 'sometimes|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        $user = auth()->user();
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        if ((int) $vehicule->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => "Vous n'êtes pas autorisé à modifier ce véhicule.",
            ], 403);
        }

        if (
            $request->has('matricule')
            && $request->matricule !== $vehicule->matricule
            && $this->isVehicleLockedAfter48Hours($vehicule)
        ) {
            return response()->json([
                'success' => false,
                'message' => "Le matricule ne peut plus être modifié après 48h.",
            ], 403);
        }

        if(!empty($user->gestionnaire_de_flotte_id)){
            $vehicule->gestionnaire_de_flotte_id = $user->gestionnaire_de_flotte_id;
            $vehicule->provenance = 'flotte';
            $vehicule->provenance_by = NULL;
        }
        DB::beginTransaction();
        try {
            // Mise à jour des champs si présents
            if ($request->has('matricule')) $vehicule->matricule = $request->matricule;
            if ($request->has('carte_grise')) $vehicule->carte_grise = $request->carte_grise;
            if ($request->has('type_de_vehicule_id')) $vehicule->type_de_vehicule_id = $request->type_de_vehicule_id;
            if ($request->has('marque_id')) $vehicule->marque_id = $request->marque_id;
            if ($request->has('type_de_carburant_id')) $vehicule->type_de_carburant_id = $request->type_de_carburant_id;
            if ($request->has('couleur')) $vehicule->couleur = $request->couleur;
			if ($request->has('modele')) $vehicule->modele = $request->modele;
            
    
            // Mise à jour des photos
            if ($request->hasFile('photos')) {
                // Supprimer les anciennes photos
                if ($vehicule->photos) {
                    foreach (json_decode($vehicule->photos, true) as $photo) {
                        if (!empty($photo)) {
                            $this->wasabiService->deleteFile($photo);
                        }
                    }
                }
            
                // Sauvegarder les nouvelles photos
                $photosPaths = [];
                foreach ($request->file('photos') as $photo) {
                    $photosPaths[] = $this->wasabiService->uploadFile(
                        $photo,
                        'images/vehicule',
                        'vehicule'
                    );
                }
            
                // Sauvegarder les chemins dans la base de données
                $vehicule->photos = json_encode($photosPaths);
            }
            
    
            $vehicule->save();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Véhicule mis à jour avec succès.',
                'vehicule' => $this->attachVehiculePhotoUrls($vehicule),
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise à jour du véhicule.",
                'dev' => $e->getMessage(),
            ], 500);
        }
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
    

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'vehicule_id' => 'required|exists:vehicules,id',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        $id = $request->vehicule_id;
    
        // Récupérer le véhicule
        $vehicule = Vehicule::find($id);
        if (!$vehicule) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
            ], 404);
        }

        $user = auth()->user();
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        if ((int) $vehicule->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => "Vous n'êtes pas autorisé à supprimer ce véhicule.",
            ], 403);
        }

        if(!empty($vehicule->provenance_by) && $vehicule->provenance_by == 1){
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer ce véhicule car il a été créé par un gestionnaire de flotte.',
            ], 403);
        }

        $deleteCheck = $this->checkVehicleDeletionAllowed($user);
        if (!$deleteCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $deleteCheck['message'],
                'data' => $deleteCheck['data'],
            ], $deleteCheck['status']);
        }
    
        DB::beginTransaction();
        try {
            // Supprimer les photos associées
            if ($vehicule->photos) {
                foreach ((array) $vehicule->photos as $photo) {
                    if (!empty($photo)) {
                        $this->wasabiService->deleteFile($photo);
                    }
                }
            }
    
            // Supprimer les alertes liées (si nécessaire)
            Alert::where('id', $id)->delete();

            VehiculeDeletion::create([
                'user_id' => $user->id,
                'vehicule_id' => $vehicule->id,
                'matricule' => $vehicule->matricule,
                'deleted_at' => now(),
            ]);
    
            // Supprimer le véhicule
            $vehicule->delete();
    
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

    private function checkVehicleQuota($user)
    {
        $abonnement = AbonnementUsager::where('user_id', $user->id)
            ->where('statut', 1)
            ->with('forfait')
            ->latest()
            ->first();

        if (!$abonnement || !$abonnement->forfait) {
            return [
                'allowed' => false,
                'status' => 403,
                'message' => "Aucun abonnement actif trouvé. Vous ne pouvez pas enregistrer de véhicule.",
                'quota' => null,
            ];
        }

        if ($abonnement->date_fin && $abonnement->date_fin->isPast()) {
            return [
                'allowed' => false,
                'status' => 403,
                'message' => "Votre abonnement est expiré. Vous ne pouvez pas enregistrer de véhicule.",
                'quota' => null,
            ];
        }

        $maxVehicules = (int) ($abonnement->forfait->nombre_vehicule ?? 0);
        $vehiculesEnregistres = Vehicule::where('user_id', $user->id)->count();

        if ($vehiculesEnregistres >= $maxVehicules) {
            return [
                'allowed' => false,
                'status' => 403,
                'message' => "Vous avez atteint le nombre maximum de véhicules autorisé par votre forfait.",
                'quota' => [
                    'maximum' => $maxVehicules,
                    'utilise' => $vehiculesEnregistres,
                    'restant' => 0,
                    'forfait_id' => $abonnement->forfait->id,
                    'forfait' => $abonnement->forfait->libelle,
                ],
            ];
        }

        return [
            'allowed' => true,
            'status' => 200,
            'message' => null,
            'quota' => [
                'maximum' => $maxVehicules,
                'utilise' => $vehiculesEnregistres,
                'restant' => $maxVehicules - $vehiculesEnregistres,
                'forfait_id' => $abonnement->forfait->id,
                'forfait' => $abonnement->forfait->libelle,
            ],
        ];
    }

    private function checkVehicleDeletionAllowed($user)
    {
        $abonnement = AbonnementUsager::where('user_id', $user->id)
            ->where('statut', 1)
            ->with('forfait')
            ->latest()
            ->first();

        if (!$abonnement || !$abonnement->forfait) {
            return [
                'allowed' => false,
                'status' => 403,
                'message' => "Aucun abonnement actif trouvé. Vous ne pouvez pas supprimer de véhicule.",
                'data' => null,
            ];
        }

        if ($abonnement->date_fin && $abonnement->date_fin->isPast()) {
            return [
                'allowed' => false,
                'status' => 403,
                'message' => "Votre abonnement est expiré. Vous ne pouvez pas supprimer de véhicule.",
                'data' => null,
            ];
        }

        $forfait = $abonnement->forfait;
        $isFreemium = (int) $forfait->id === 1 || strtoupper((string) $forfait->libelle) === 'FREEMIUM';

        if ($isFreemium) {
            return [
                'allowed' => false,
                'status' => 403,
                'message' => "La suppression de véhicule n'est pas disponible avec le forfait FREEMIUM.",
                'data' => [
                    'forfait_id' => $forfait->id,
                    'forfait' => $forfait->libelle,
                ],
            ];
        }

        $lastDeletion = VehiculeDeletion::where('user_id', $user->id)
            ->orderByDesc('deleted_at')
            ->first();

        if ($lastDeletion && $lastDeletion->deleted_at && $lastDeletion->deleted_at->gt(now()->subMonths(6))) {
            $nextDeletionAt = $lastDeletion->deleted_at->copy()->addMonths(6);

            return [
                'allowed' => false,
                'status' => 403,
                'message' => "Vous ne pouvez supprimer qu'un véhicule tous les 6 mois.",
                'data' => [
                    'derniere_suppression' => $lastDeletion->deleted_at->format('Y-m-d H:i:s'),
                    'prochaine_suppression_possible' => $nextDeletionAt->format('Y-m-d H:i:s'),
                ],
            ];
        }

        return [
            'allowed' => true,
            'status' => 200,
            'message' => null,
            'data' => [
                'forfait_id' => $forfait->id,
                'forfait' => $forfait->libelle,
            ],
        ];
    }

    private function isVehicleLockedAfter48Hours($vehicule)
    {
        return $vehicule->created_at && $vehicule->created_at->lt(now()->subHours(48));
    }
    
	/**
     * Display a listing of the resource.
     */
    public function indexVehiculeConcessionnaire(Request $request)
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
        $vehicules = Vehicule_concessionnaire::orderBy('id', 'desc')
		->with('marque', 'concessionnaire')
        ->get();
		
		//dd($vehicules);

        // Vérifier si des établissements existent
        if ($vehicules->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun vehicule enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des vehicules.',
            'vehicules' => $vehicules->map(function ($vehicule) {
                return $this->attachVehiculeConcessionnairePhotoUrls($vehicule);
            }),
        ], 200);
    }
    
	
	/**
     * Display a listing of the resource.
     */
    public function indexVehiculeByConcessionnaire(Request $request, $id)
    {
        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }
		
		$concessionnaire = Concessionnaire::where('id', $id)->first();
		if (empty($concessionnaire)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les établissements triés par ID décroissant
        $vehicules = Vehicule_concessionnaire::where('concessionnaire_id', $concessionnaire->id)
		->orderBy('id', 'desc')
		->with('marque', 'concessionnaire')
        ->get();
		
		//dd($vehicules);

        // Vérifier si des établissements existent
        if ($vehicules->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun vehicule enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des vehicules.',
            'vehicules' => $vehicules->map(function ($vehicule) {
                return $this->attachVehiculeConcessionnairePhotoUrls($vehicule);
            }),
        ], 200);
    }

    protected function attachVehiculeConcessionnairePhotoUrls($vehicule)
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

        $photoUrls = array_values(array_filter(array_map(function ($photo) {
            return $this->vehiculeConcessionnairePhotoUrl($photo);
        }, $photos)));

        $vehicule->photos = $photoUrls;
        $vehicule->photo_urls = $photoUrls;

        return $vehicule;
    }

    protected function vehiculeConcessionnairePhotoUrl(?string $photo): ?string
    {
        if (empty($photo)) {
            return null;
        }

        if (filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }

        $path = $this->normalizeVehiculeConcessionnairePhotoPath($photo);

        try {
            return $this->wasabiService->temporaryUrl($path) ?? $this->wasabiPublicUrl($path);
        } catch (\Throwable $e) {
            if (Str::contains($photo, '/')) {
                return $this->wasabiPublicUrl($path);
            }

            return asset('images/vehicules/' . ltrim($photo, '/'));
        }
    }

    protected function normalizeVehiculeConcessionnairePhotoPath(string $photo): string
    {
        if (Str::contains($photo, '/')) {
            return ltrim($photo, '/');
        }

        return 'images/vehicules/' . ltrim($photo, '/');
    }

    protected function wasabiPublicUrl(string $path): string
    {
        return rtrim((string) config('wasabi.url'), '/') . '/' . ltrim($path, '/');
    }
}
