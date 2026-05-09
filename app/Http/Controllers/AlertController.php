<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Contact_util;
use App\Models\Commissariat;
use App\Models\Prefecture;
use App\Models\Commune;
use App\Models\Vehicule;
use App\Models\Incident;
use App\Models\Revision_technique;
use App\Models\VisiteTechnique;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Constat;
use App\Models\Garage_flotte;
use App\Services\FirebaseNotificationService;
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

class AlertController extends Controller
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
        $alerts = Alert::where('user_id', $user->id)
		->with('vehicule', 'type_alert')
        ->orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($alerts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune alert enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des alerts.',
            'alerts' => $alerts->map(function ($alert) {
                return $this->attachAlertRelationsMediaUrls($alert);
            }),
        ], 200);
    }

    protected function attachAlertRelationsMediaUrls($alert)
    {
        if (!$alert) {
            return $alert;
        }

        if (!empty($alert->vehicule)) {
            $alert->vehicule = $this->attachVehiculePhotoUrls($alert->vehicule);
        }

        return $alert;
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

    protected function attachVisiteTechniqueMediaUrls($visiteTechnique)
    {
        if (!$visiteTechnique) {
            return $visiteTechnique;
        }

        $visiteTechnique->logo = !empty($visiteTechnique->logo)
            ? $this->wasabiService->temporaryUrl($this->normalizeVisiteTechniqueLogoPath($visiteTechnique->logo))
            : null;

        return $visiteTechnique;
    }

    protected function normalizeVisiteTechniqueLogoPath($path)
    {
        if (empty($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (Str::contains($path, '/')) {
            return $path;
        }

        return 'visite_techniques/logo/' . ltrim($path, '/');
    }

    /**
     * Display a listing of the resource.
     */
    public function indexContactUtil()
    {
        $user = auth()->user();
        // Vérifier si des contacts utilisateurs existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les contacts utilisateurs
        $contacts = Contact_util::all();

        // Vérifier si des contacts util existent
        if ($contacts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun contact util enregistré pour le moment.',
            ], 404);
        }

		return response()->json([
            'success' => true,
            'message' => 'Liste des contacts utilisateurs.',
            'contacts' => $contacts,
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function getAlertByType(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'type_alert_id' => 'required|exists:type_alerts,id',
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
        $alerts = Alert::where('user_id', $user->id)
        ->where('type_alert_id', $request->type_alert_id)
        ->orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($alerts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune alert enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des alerts.',
            'alerts' => $alerts,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'vehicule_id' => 'required|exists:vehicules,id',
            'type_alert_id' => 'required|exists:type_alerts,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
			'kilometrage' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        // Vérification si l'utilisateur est authentifié
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Création d'une alert
            $alert = new Alert();
            $alert->vehicule_id = $request->vehicule_id;
            $alert->type_alert_id = $request->type_alert_id;
            $alert->date_debut = $request->date_debut;
            $alert->date_fin = $request->date_fin;
            $alert->kilometrage = $request->kilometrage;
            $alert->user_id = $user->id;

            if(!empty($user->gestionnaire_de_flotte_id)){
                $alert->gestionnaire_de_flotte_id = $user->gestionnaire_de_flotte_id;
                $alert->provenance = 'flotte';
            }

            $alert->save();

            // Envoyer une notification push Firebase
            try {
                $firebaseService = new FirebaseNotificationService();

                // Charger les relations nécessaires
                $alert->load(['type_alert', 'vehicule']);

                $typeAlertName = $alert->type_alert ? ($alert->type_alert->nom ?? $alert->type_alert->libelle ?? 'Alerte') : 'Alerte';
                $vehiculeName = $alert->vehicule ? ($alert->vehicule->immatriculation ?? 'Véhicule') : 'Véhicule';

                $title = '🚨 Nouvelle alerte créée';
                $body = "Une alerte de type {$typeAlertName} a été créée pour votre véhicule {$vehiculeName}.";

                $data = [
                    'type' => 'alert',
                    'alert_id' => $alert->id,
                    'vehicule_id' => $alert->vehicule_id,
                    'type_alert_id' => $alert->type_alert_id,
                ];

                $firebaseService->sendToUser($user, $title, $body, $data);
            } catch (\Exception $e) {
                // Log l'erreur mais ne pas faire échouer la création de l'alerte
                \Log::error('Erreur lors de l\'envoi de la notification push: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Alert enregistré avec succès.',
                'alert' => $alert,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement de l'alert.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function update(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'alert_id' => 'required|exists:alerts,id',
            'vehicule_id' => 'required|exists:vehicules,id',
            'type_alert_id' => 'required|exists:type_alerts,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
			'kilometrage' => "nullable"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérifier si l'utilisateur est authentifié
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        $user = auth()->user();

        // Trouver l'alerte à mettre à jour
        $alert = Alert::find($request->alert_id);

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alerte introuvable.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Mettre à jour les informations de l'alerte
            $alert->vehicule_id = $request->vehicule_id;
            $alert->type_alert_id = $request->type_alert_id;
            $alert->date_debut = $request->date_debut;
            $alert->date_fin = $request->date_fin;
            $alert->kilometrage = $request->kilometrage;
            $alert->user_id = $user->id;

            $alert->save();

            DB::commit();

            // Retourner une réponse de succès
            return response()->json([
                'success' => true,
                'message' => 'Alerte mise à jour avec succès.',
                'alert' => $alert,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise à jour de l'alerte.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'alert_id' => 'required|exists:alerts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérifier si l'utilisateur est authentifié
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        $user = auth()->user();

        // Trouver l'alerte à supprimer
        $alert = Alert::find($request->alert_id);

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alerte introuvable.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Supprimer l'alerte
            $alert->delete();

            DB::commit();

            // Retourner une réponse de succès
            return response()->json([
                'success' => true,
                'message' => 'Alerte supprimée avec succès.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la suppression de l'alerte.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

	    /**
     * Store a newly created resource in storage.
     */
    public function storeConstat(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'commissariat_id' => 'required|exists:commissariats,id',
            'prefecture_id' => 'required|exists:prefectures,id',
            'photos' => 'required|array|min:4|max:4', // Permet exactement 4 fichiers
            'photos.*' => 'required|file|image|max:10048', // Vérifie chaque fichier
            'longitude' => 'required|string',
            'latitude' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérifier si l'utilisateur est authentifié
        $user = auth()->user();
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // Vérifier si le champ photos contient exactement 4 fichiers
        if (!$request->hasFile('photos') || count($request->file('photos')) !== 4) {
            return response()->json([
                'success' => false,
                'message' => 'Le champ photos doit contenir exactement 4 images.',
            ], 422);
        }

        // Vérifier si le commissariat existe
        $commissariat = Commissariat::find($request->commissariat_id);
        if (empty($commissariat)) {
            return response()->json([
                'success' => false,
                'message' => 'Commissariat introuvable.',
            ], 404);
        }

        // Vérifier si la prefecture existe
        $prefecture = Prefecture::find($request->prefecture_id);
        if (empty($prefecture)) {
            return response()->json([
                'success' => false,
                'message' => 'Prefecture introuvable.',
            ], 404);
        }
        DB::beginTransaction();
        try {
            // Création du constat
            $constat = new Constat();
            $constat->commissariat_id = $commissariat->id;
            $constat->prefecture_id = $prefecture->id;
            $constat->longitude = $request->longitude;
            $constat->latitude = $request->latitude;
            $constat->user_id = $user->id;

            // Sauvegarde des photos
            $photosPaths = [];
            foreach ($request->file('photos') as $photo) {
                $photosPaths[] = $this->wasabiService->uploadFile(
                    $photo,
                    'constats/photos',
                    'constat'
                );
            }

            $constat->photos = $photosPaths;
            $constat->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Constat enregistré avec succès.',
                'constat' => $constat,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement du constat.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

	    /**
     * Store a newly created resource in storage.
     */
    public function storeIncident(Request $request)
	{
		// Validation des données d'entrée
		$validator = Validator::make($request->all(), [
			'user_id' => 'required|exists:users,id',
			'sapeur_pompier_id' => 'required|exists:sapeur_pompiers,id',
			'photos' => 'required|array|min:4|max:4',
			'photos.*' => 'required|file|mimes:jpeg,png,jpg,gif|max:10048', // Chaque fichier doit être une image valide
			'longitude' => 'required|string',
			'latitude' => 'required|string',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation échouée.',
				'errors' => $validator->errors(),
			], 422);
		}

		// Vérifiez si l'utilisateur est authentifié
		$user = auth()->user();
		if (empty($user)) {
			return response()->json([
				'success' => false,
				'message' => 'Utilisateur introuvable.',
			], 404);
		}

		// Vérifiez si des fichiers sont présents dans 'photos'
		if (!$request->hasFile('photos')) {
			return response()->json([
				'success' => false,
				'message' => 'Le champ photos est requis.',
			], 422);
		}

		DB::beginTransaction();
		try {
			// Création du nouvel incident
			$incident = new Incident();
			$incident->sapeur_pompier_id = $request->sapeur_pompier_id;
			$incident->longitude = $request->longitude;
			$incident->latitude = $request->latitude;
			$incident->user_id = $user->id;

			// Sauvegarde des photos
			$photosPaths = [];
			foreach ($request->file('photos') as $photo) {
				$photosPaths[] = $this->wasabiService->uploadFile(
					$photo,
					'incidents/photos',
					'incident'
				);
			}

			$incident->photos = $photosPaths;
			$incident->save();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Incident enregistré avec succès.',
				'incident' => $incident,
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => "Une erreur est survenue lors de l'enregistrement de l'incident.",
				'dev' => $e->getMessage(),
			], 500);
		}
	}

    /**
     * Display a listing of the resource.
     */
    public function indexGarageGestionnaireDeFlotte()
    {
        $user = auth()->user();
        // Vérifier si des garages gestionnaires de flotte existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les garages gestionnaires de flotte
        $garages = Garage_flotte::where('gestionnaire_de_flotte_id', $user->gestionnaire_de_flotte_id)
        ->orderBy('id', 'desc')
        ->get();

        // Vérifier si des garages gestionnaires de flotte existent
        if ($garages->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun garage gestionnaire de flotte enregistré pour le moment.',
            ], 404);
        }

		return response()->json([
            'success' => true,
            'message' => 'Liste des garages gestionnaires de flotte.',
            'garages' => $garages,
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexRevisionTechnique()
    {
        $user = auth()->user();
        // Vérifier si des revision technique existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les revision technique
        $revision_technique = Revision_technique::orderBy('id', 'desc')
        ->with('ville', 'commune')
        ->get();

        // Vérifier si des revision technique existent
        if ($revision_technique->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun revision technique enregistré pour le moment.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des revision technique.',
            'revision_technique' => $revision_technique,
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexVisiteTechnique()
    {
        $user = auth()->user();
        // Vérifier si des visite technique existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les visite technique
        $visite_techniques = VisiteTechnique::where('statut', 1)
            ->orderBy('id', 'desc')
            ->with('ville', 'commune')
            ->get();

        // Vérifier si des visite technique existent
        if ($visite_techniques->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune visite technique enregistré pour le moment.',
            ], 404);
        }

        $visite_techniques->transform(function ($visiteTechnique) {
            return $this->attachVisiteTechniqueMediaUrls($visiteTechnique);
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des visite technique.',
            'visite_techniques' => $visite_techniques,
        ], 200);
    }

    /**
     * Envoyer un message WhatsApp via l'API Wassenger
     */
    public function sendWhatsAppMessage(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Configuration de l'API Wassenger
        $apiUrl = 'https://api.wassenger.com/v1/messages';
        $token = '11aa75a1de8f22a6c05e5b49eeb309b48329258699f05e419624bff1d0fcc9940058293b92a6fc95';

        // Données à envoyer
        $data = [
            'phone' => $request->phone,
            'message' => $request->message
        ];

        // Initialisation de cURL
        $curl = curl_init();

        // Configuration des options cURL
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Token: ' . $token
            ],
        ]);

        // Exécution de la requête
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        // Fermeture de cURL
        curl_close($curl);

        // Vérification des erreurs cURL
        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message WhatsApp.',
                'error' => $error,
            ], 500);
        }

        // Décodage de la réponse
        $responseData = json_decode($response, true);

        // Vérification du code de statut HTTP
        if ($httpCode >= 200 && $httpCode < 300) {
            return response()->json([
                'success' => true,
                'message' => 'Message WhatsApp envoyé avec succès.',
                'data' => $responseData,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Échec de l\'envoi du message WhatsApp.',
                'http_code' => $httpCode,
                'response' => $responseData,
            ], $httpCode);
        }
    }

    /**
     * Envoyer une alerte avec notification WhatsApp
     */
    // public function storeWithWhatsAppNotification(Request $request)
    // {
    //     // Validation des données d'entrée pour l'alerte
    //     $validator = Validator::make($request->all(), [
    //         'vehicule_id' => 'required|exists:vehicules,id',
    //         'type_alert_id' => 'required|exists:type_alerts,id',
    //         'date_debut' => 'required|date',
    //         'date_fin' => 'required|date',
    //         'kilometrage' => 'nullable',
    //         'phone' => 'required|string', // Numéro pour WhatsApp
    //         'whatsapp_message' => 'nullable|string', // Message personnalisé
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation échouée.',
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }

    //     $user = auth()->user();
    //     if (!auth()->check()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Utilisateur introuvable',
    //         ], 404);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         // Création de l'alerte
    //         $alert = new Alert();
    //         $alert->vehicule_id = $request->vehicule_id;
    //         $alert->type_alert_id = $request->type_alert_id;
    //         $alert->date_debut = $request->date_debut;
    //         $alert->date_fin = $request->date_fin;
    //         $alert->kilometrage = $request->kilometrage;
    //         $alert->user_id = $user->id;

    //         if (!empty($user->gestionnaire_de_flotte_id)) {
    //             $alert->gestionnaire_de_flotte_id = $user->gestionnaire_de_flotte_id;
    //             $alert->provenance = 'flotte';
    //         }

    //         $alert->save();

    //         // Préparation du message WhatsApp
    //         $defaultMessage = "🚨 ALERTE VÉHICULE 🚨\n\n";
    //         $defaultMessage .= "Type d'alerte: " . $alert->type_alert->nom . "\n";
    //         $defaultMessage .= "Date de début: " . $alert->date_debut . "\n";
    //         $defaultMessage .= "Date de fin: " . $alert->date_fin . "\n";
    //         if ($alert->kilometrage) {
    //             $defaultMessage .= "Kilométrage: " . $alert->kilometrage . " km\n";
    //         }
    //         $defaultMessage .= "\nVeuillez prendre les mesures nécessaires.";

    //         $whatsappMessage = $request->whatsapp_message ?? $defaultMessage;

    //         // Envoi du message WhatsApp
    //         $whatsappResponse = $this->sendWhatsAppMessageInternal($request->phone, $whatsappMessage);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Alerte enregistrée et notification WhatsApp envoyée avec succès.',
    //             'alert' => $alert,
    //             'whatsapp_sent' => $whatsappResponse['success'],
    //             'whatsapp_response' => $whatsappResponse,
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'success' => false,
    //             'message' => "Une erreur est survenue lors de l'enregistrement de l'alerte.",
    //             'dev' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /**
     * Méthode interne pour envoyer un message WhatsApp
     */
    private function sendWhatsAppMessageInternal($phone, $message)
    {
        // Configuration de l'API Wassenger
        $apiUrl = 'https://api.wassenger.com/v1/messages';
        $token = '11aa75a1de8f22a6c05e5b49eeb309b48329258699f05e419624bff1d0fcc9940058293b92a6fc95';

        // Données à envoyer
        $data = [
            'phone' => $phone,
            'message' => $message
        ];

        // Initialisation de cURL
        $curl = curl_init();

        // Configuration des options cURL
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Token: ' . $token
            ],
        ]);

        // Exécution de la requête
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        // Fermeture de cURL
        curl_close($curl);

        // Retour de la réponse
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
            ];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $responseData,
        ];
    }

}
