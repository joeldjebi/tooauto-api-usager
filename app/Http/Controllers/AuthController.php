<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Chauffeur;
use App\Models\Verify_code;
use App\Models\AbonnementUsager;
use App\Models\Forfait_usager;
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
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;
use App\Services\WasabiService;
use App\Services\ReductionCardService;

class AuthController extends Controller
{
    protected $wasabiService;
    protected $smsService;
    protected $reductionCardService;

        /**
     * Create a new AuthController instance.
     *
     * @return void
     */
	public function __construct(WasabiService $wasabiService, SmsService $smsService, ReductionCardService $reductionCardService) {
        $this->wasabiService = $wasabiService;
        $this->smsService = $smsService;
        $this->reductionCardService = $reductionCardService;
        $this->middleware('auth:api', ['except' => [
            'login','loginPro', 'register', 'sendOtpForRegister', 'verifyOtp',
            'verifyNumberPasswordForget', 'passwordForgetUpdate',
            'sendOtpForPasswordForget','indexPaysAll', 'verifyOtpPasswordForget',
            'checkEmailExists', 'checkEmailExistsPro',
            'updateFirstLogin', 'updateFirstLoginPro'
        ]]);
    }

    /**
     * Mettre à jour le token FCM de l'utilisateur mobile.
     */
    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token FCM mis à jour avec succès',
            'data' => [
                'user_id' => $user->id,
                'fcm_token' => $user->fcm_token,
            ],
        ]);
    }

    public function checkEmailExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'success' => true,
            'message' => $exists ? 'Cet email existe déjà.' : 'Cet email est disponible.',
            'exists' => $exists,
        ], 200);
    }

    public function checkEmailExistsPro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $exists = Chauffeur::where('email', $request->email)->exists();

        return response()->json([
            'success' => true,
            'message' => $exists ? 'Cet email existe déjà.' : 'Cet email est disponible.',
            'exists' => $exists,
        ], 200);
    }

	    public function updateFirstLogin(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_login' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $user->first_login = (int) $request->first_login;

        if (!$user->save()) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise à jour.",
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'first_login mis à jour avec succès.',
            'user' => $user,
        ], 200);
    }

    public function updateFirstLoginPro(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_login' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $chauffeur = Chauffeur::find($id);

        if (empty($chauffeur)) {
            return response()->json([
                'success' => false,
                'message' => 'Chauffeur introuvable.',
            ], 404);
        }

        $chauffeur->first_login = (int) $request->first_login;

        if (!$chauffeur->save()) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise à jour.",
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'first_login mis à jour avec succès.',
            'user' => $chauffeur,
        ], 200);
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtpForRegister(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'indicatif' => 'required|string',
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Rechercher l'utilisateur avec l'indicatif et le numéro
        $userNumberVerify = User::where('indicatif', $request->indicatif)
                                ->where('mobile', $request->mobile)
                                ->first();

        if (!empty($userNumberVerify)) {
            return response()->json([
                'error' => true,
                'message' => 'Numéro de téléphone existe, veuillez vous connecter.',
            ], 404);
        }

        // Générer le code de confirmation
        $confirmationCode = rand(1000, 9999);
        $mobileWithIndicatif = $request->indicatif . $request->mobile;

        // Construire le message
        $message = strtoupper("Votre code de confirmation: " . $confirmationCode);

        // Envoyer le SMS
        try {
            $smsResponse = $this->sendSmsMtarget($message, $mobileWithIndicatif);

            // Enregistrer le code de vérification
            $verifyCode = new Verify_code();
            $verifyCode->code = $confirmationCode;
            $verifyCode->mobile = $mobileWithIndicatif;
            $verifyCode->statut = 0;

            if (!$verifyCode->save()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une erreur est survenue lors de l\'enregistrement du code.',
                ], 500);
            }
        } catch (\Exception $e) {
            // Enregistrer l'erreur dans les logs
            \Log::error('Erreur lors de l\'envoi du SMS : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi du SMS.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Retourner la réponse avec le code de confirmation
        return response()->json([
            'success' => true,
            'message' => 'Code de confirmation envoyé par SMS.',
            'code' => $confirmationCode,
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Rechercher l'OTP dans la table Verify_codes
        $verifyCode = Verify_code::where('mobile', $request->mobile)
                                ->where('code', $request->otp)
                                ->where('statut', 0)
                                ->first();

        // Vérifier si l'OTP existe et a le statut 0
        if (empty($verifyCode)) {
            return response()->json([
                'success' => false,
                'message' => 'Le code OTP est invalide ou a déjà été utilisé.',
            ], 404);
        }

        // Mettre à jour le statut de l'OTP pour indiquer qu'il a été utilisé
        $verifyCode->statut = 1;

        if (!$verifyCode->save()) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la validation de l\'OTP.',
            ], 500);
        }

        // Retourner une réponse réussie
        return response()->json([
            'success' => true,
            'message' => 'Le code OTP est valide.',
        ], 200);
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Récupération des données d'authentification
        $credentials = $request->only(['mobile', 'password', 'indicatif']);

        // Enlever l'indicatif pour obtenir uniquement le numéro de téléphone
        $mobileWithoutIndicatif = $credentials['mobile']; // Numéro sans l'indicatif
        $user = User::where('mobile', $mobileWithoutIndicatif)->first(); // Recherche sans l'indicatif

        if ($user && $user->statut != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur désactivé.',
            ], 401);
        }

        // Vérifier si l'utilisateur existe et comparer le mot de passe
        if ($user && Hash::check($request->password, $user->password)) {
            $user = $this->attachAvatarUrl($user);

            // Authentification réussie
            $token = JWTAuth::fromUser($user);

            // Calculer l'heure d'expiration du token
            $expirationTime = now()->addMinutes(config('jwt.ttl'))->timestamp;

			// Récupérer l'abonnement de l'utilisateur
        	$abonnement = $this->getUserAbonnement($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur authentifié avec succès.',
                'access_token' => $token,
                'expiration_time' => $expirationTime, // Date d'expiration
                'user' => $user,
				'abonnement' => $abonnement
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects. Veuillez vérifier votre numéro et votre mot de passe.',
        ], 401);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginPro(Request $request)
    {
        // Récupération des données d'authentification
        $credentials = $request->only(['mobile', 'password', 'indicatif']);

        // Enlever l'indicatif pour obtenir uniquement le numéro de téléphone
        $mobileWithoutIndicatif = $credentials['mobile']; // Numéro sans l'indicatif
        $user = Chauffeur::where('mobile', $mobileWithoutIndicatif)->first(); // Recherche sans l'indicatif

        // $mobileWithIndicatif = '+2250758754662';//'$credentials['indicatif']' . $mobileWithoutIndicatif; // Numéro avec l'indicatif
        // $message = "votre code de verification est 1234";

        // try {
        //     $res = $this->sendSmsMtarget($message, $mobileWithIndicatif);
        //     dd($res);
        // } catch (\Exception $e) {
        //     dd($e->getMessage());
        // }

        // Vérifier si l'utilisateur existe et comparer le mot de passe
        if ($user && Hash::check($request->password, $user->password)) {
            // Authentification réussie
            $token = JWTAuth::fromUser($user);

            // Calculer l'heure d'expiration du token
            $expirationTime = now()->addMinutes(config('jwt.ttl'))->timestamp;

			// Récupérer l'abonnement de l'utilisateur
        	$abonnement = $this->getUserAbonnement($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur authentifié avec succès.',
                'access_token' => $token,
                'expiration_time' => $expirationTime, // Date d'expiration
                'user' => $user,
				'abonnement' => $abonnement
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects. Veuillez vérifier votre numéro et votre mot de passe.',
        ], 401);
    }

	private function getUserAbonnement($userId)
	{
		try {
			// Récupérer le dernier abonnement de l'utilisateur
			$abonnement = AbonnementUsager::where('user_id', $userId)
				->where('statut', 1)
				->with('forfait.avantageUsager') // Charger la relation avec le forfait et ses avantages
				->latest() // Prendre le plus récent
				->first();

			if ($abonnement) {
				$forfait = $abonnement->forfait;
				$avantage = $forfait ? $forfait->avantageUsager : null;
				$avantages = $avantage ? $this->formatAvantages($avantage->avantages) : [];

				return [
					'id' => $abonnement->id,
					'forfait' => $forfait ? [
						'id' => $forfait->id,
						'nom' => $forfait->libelle,
						'libelle' => $forfait->libelle,
						'duree' => $forfait->duree,
						'prix' => $forfait->prix,
						'statut' => $forfait->statut,
						'forfait_avantage_usager_id' => $forfait->forfait_avantage_usager_id,
						'avantages' => $avantages,
						'forfait_avantage_usager' => $avantage ? [
							'id' => $avantage->id,
							'avantages' => $avantages,
							'available' => $avantage->available,
							'created_at' => $avantage->created_at,
							'updated_at' => $avantage->updated_at,
						] : null,
					] : null,
					'date_debut' => $abonnement->date_debut->format('Y-m-d'),
					'date_fin' => $abonnement->date_fin->format('Y-m-d'),
					'is_free' => (bool)$abonnement->is_free,
					'statut' => $abonnement->statut,
					'est_expire' => $abonnement->date_fin < now() // Ajout d'un indicateur d'expiration
				];
			}

			return null;
		} catch (\Exception $e) {
			\Log::error('Erreur lors de la récupération de l\'abonnement: ' . $e->getMessage());
			return null;
		}
	}

	private function formatAvantages($avantages)
	{
		return array_values(array_filter(array_map('trim', explode(';', $avantages ?? ''))));
	}

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'indicatif' => 'required|string',
            'mobile' => 'required|numeric|unique:users',
            'password' => 'required|string|min:6',
            'otp' => 'required|numeric',
            'is_whatsapp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérification de l'OTP avec statut 1 (valide)
        $verifyCode = Verify_code::where('mobile', $request->indicatif . $request->mobile)
            ->where('code', $request->otp)
            ->where('statut', 1) // Statut valide
            ->first();

        if (empty($verifyCode)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP invalide ou déjà utilisé.',
            ], 400); // Code 400 pour mauvaise requête
        }
        // Utilisation d'une transaction pour garantir l'intégrité des données
        DB::beginTransaction();
        try {
            $abonnement = null;

            // Création de l'utilisateur
            $user = new User();
            $user->uuid = (string) Str::uuid();
            $user->indicatif = $request->indicatif;
            $user->mobile = $request->mobile;
            $user->password = bcrypt($request->password); // Hash sécurisé du mot de passe
			$user->is_whatsapp = $request->is_whatsapp;

            $user->save();

            if ($this->shouldAssignRegisterAbonnement()) {
                $forfait = $this->getRegisterAbonnementForfait();
                $dateDebut = now();
                $dateFin = now()->addMonths((int) $forfait->duree);

                $abonnement = AbonnementUsager::create([
                    'user_id' => $user->id,
                    'forfait_id' => $forfait->id,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                    'statut' => 1,
                    'is_free' => 1,
                ]);

                $this->reductionCardService->assignCardsToSubscription($abonnement);
            }

            // Commit de la transaction
            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Utilisateur enregistré avec succès.',
                'user' => $user,
            ];

            if ($abonnement) {
                $response['abonnement'] = $abonnement;
            }

            return response()->json($response, 201); // Utilisation du code HTTP 201 pour "Created"

        } catch (\Exception $e) {
            // Rollback de la transaction en cas d'erreur
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement de l'utilisateur.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    private function shouldAssignRegisterAbonnement(): bool
    {
        return (bool) config('services.register_auto_abonnement.enabled', false);
    }

    private function getRegisterAbonnementForfait(): Forfait_usager
    {
        $libelle = Str::upper(trim((string) config('services.register_auto_abonnement.forfait', 'FREEMIUM')));
        $allowedForfaits = ['FREEMIUM', 'PERSONNEL', 'FAMILLE'];

        if (!in_array($libelle, $allowedForfaits, true)) {
            throw new \RuntimeException('Configuration REGISTER_AUTO_ABONNEMENT_FORFAIT invalide.');
        }

        $forfait = Forfait_usager::whereRaw('UPPER(TRIM(libelle)) = ?', [$libelle])->first();

        if (!$forfait) {
            throw new \RuntimeException('Forfait automatique introuvable: ' . $libelle);
        }

        return $forfait;
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // Vérifier si l'email a été modifié
        $emailRule = 'nullable|email';
        if ($request->filled('email') && $request->email !== $user->email) {
            $emailRule = 'nullable|email|unique:users,email,' . $id;
        }

        // Valider les données de la requête
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string|max:255',
            'prenoms' => 'nullable|string|max:255',
            'email' => $emailRule,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Avatar (image)
			'is_whatsapp' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Mise à jour des champs basiques
        if ($request->filled('nom')) {
            $user->nom = $request->nom;
        }

        if ($request->filled('prenoms')) {
            $user->prenoms = $request->prenoms;
        }

        if ($request->filled('email')) {
            $user->email = $request->email;
        }

		if ($request->filled('is_whatsapp')) {
            $user->is_whatsapp = $request->is_whatsapp;
        }

        // Gestion de l'avatar via le service Wasabi
        if ($request->hasFile('avatar')) {
            try {
                $avatar = $request->file('avatar');

                if ($user->avatar) {
                    $legacyAvatarPath = public_path('images/avatar/' . basename($user->avatar));
                    if (file_exists($legacyAvatarPath)) {
                        unlink($legacyAvatarPath);
                    }

                    $this->wasabiService->deleteFile($user->avatar);
                }

                $avatarPath = $this->wasabiService->uploadAvatar($avatar);

                $user->avatar = $avatarPath;
            } catch (\Throwable $e) {
                Log::error('Erreur upload avatar Wasabi', [
                    'user_id' => $id,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Une erreur est survenue lors de l'envoi de l'image sur Wasabi.",
                    'dev' => $e->getMessage(),
                ], 500);
            }
        }

        // Sauvegarder les changements
        try {
            $user->save();
            $user = $this->attachAvatarUrl($user);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès.',
                'user' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise à jour de l'utilisateur.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUserPro(Request $request, $id)
    {
        $user = Chauffeur::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // Vérifier si l'email a été modifié
        $emailRule = 'nullable|email';
        if ($request->filled('email') && $request->email !== $user->email) {
            $emailRule = 'nullable|email|unique:users,email,' . $id;
        }

        // Valider les données de la requête
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string|max:255',
            'prenoms' => 'nullable|string|max:255',
            'email' => $emailRule,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Avatar (image)
			'is_whatsapp' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Mise à jour des champs basiques
        if ($request->filled('nom')) {
            $user->nom = $request->nom;
        }

        if ($request->filled('prenoms')) {
            $user->prenoms = $request->prenoms;
        }

        if ($request->filled('email')) {
            $user->email = $request->email;
        }

		if ($request->filled('is_whatsapp')) {
            $user->is_whatsapp = $request->is_whatsapp;
        }

        // Gestion de l'avatar via le service Wasabi
        if ($request->hasFile('avatar')) {
            try {
                $avatar = $request->file('avatar');

                if ($user->avatar) {
                    $legacyAvatarPath = public_path('images/avatar/' . basename($user->avatar));
                    if (file_exists($legacyAvatarPath)) {
                        unlink($legacyAvatarPath);
                    }

                    $this->wasabiService->deleteFile($user->avatar);
                }

                $avatarPath = $this->wasabiService->uploadAvatar($avatar);

                $user->avatar = $avatarPath;
            } catch (\Throwable $e) {
                Log::error('Erreur upload avatar Wasabi', [
                    'user_id' => $id,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Une erreur est survenue lors de l'envoi de l'image sur Wasabi.",
                    'dev' => $e->getMessage(),
                ], 500);
            }
        }

        // Sauvegarder les changements
        try {
            $user->save();
            $user = $this->attachAvatarUrl($user);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès.',
                'user' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise à jour de l'utilisateur.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    public function updatePassword(Request $request, $id)
    {
        // Valider les données
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8', // La confirmation vérifie si new_password correspond à password_confirmation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Récupérer l'utilisateur
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // Vérifier l'ancien mot de passe
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect.',
            ], 403);
        }

        // Vérifier si le nouveau mot de passe est différent de l'ancien
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe ne peut pas être identique à l\'ancien mot de passe.',
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->password = bcrypt($request->new_password);

        try {
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès.',
            ], 200);
        } catch (\Exception $e) {
            // Il est préférable de ne pas exposer l'erreur exacte en production
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du mot de passe.',
            ], 500);
        }
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->createNewToken(auth()->refresh());
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUser()
    {
		$abonnement = $this->getUserAbonnement(auth()->user()->id);
        $user = $this->attachAvatarUrl(auth()->user());
        return response()->json([
            'user' => $user,
			'abonnement' => $abonnement
        ]);
    }

    protected function attachAvatarUrl($user)
    {
        if ($user && !empty($user->avatar)) {
            try {
                $user->avatar = $this->wasabiService->temporaryUrl($user->avatar);
            } catch (\Throwable $e) {
                Log::warning('Impossible de generer l URL signee de l avatar', [
                    'user_id' => $user->id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $user;
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token)
    {
        if ($usr->save()) {
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => auth()->user(),
            ]);
        }
    }

    // function sendSmsMtarget($message, $reciever)
    // {
    //     $url = "https://api-public-2.mtarget.fr/messages";

    //     $username = "bwantech";
    //     $password = "x7jyKG0IJRNH";

    //     if (strpos($reciever, '+') !== 0) {
    //         $reciever = '+' . $reciever;
    //     }

    //     // Données à envoyer en POST
    //     $postData = http_build_query([
    //         'username' => $username,
    //         'password' => $password,
    //         'msisdn'   => $reciever,
    //         'msg'      => $message,
    //     ]);

    //     // Initialisation de cURL
    //     $ch = curl_init();

    //     curl_setopt_array($ch, [
    //         CURLOPT_URL            => $url,
    //         CURLOPT_RETURNTRANSFER => true,  // Pour récupérer la réponse
    //         CURLOPT_POST           => true,
    //         CURLOPT_POSTFIELDS     => $postData,
    //         CURLOPT_HTTPHEADER     => [
    //             "Content-Type: application/x-www-form-urlencoded",
    //         ],
    //     ]);

    //     // Exécution
    //     $response = curl_exec($ch);

    //     // Gestion des erreurs
    //     if (curl_errno($ch)) {
    //         throw new \Exception("Erreur cURL : " . curl_error($ch));
    //     }

    //     curl_close($ch);

    //     return $response;
    // }
    function sendSmsMtarget_($message, $reciever) {
        $apiUrl = "http://jaimeboutik.com/API/";
        // $message = "Vos accès:\nUsername: $recipients\nMot de passe: $password";
        $sender = 'TOOauto';
        $media = '';
        $unicode = 0;
        $mms = 0;
        $reciever = '002250758754662';

        // $url = "http://jaimeboutik.com/API/?action=compose&username=tooauto&api_key=059c00197bc410ccc377b9c68aaef51d:H7Jy5xiF3YvYSSopBdf0B6PiMYEcjLRT&sender=$sender&to=$reciever&message=$message&mms=$mms&unicode=$unicode&media=$media";

        $params = [
            'action'    => 'compose',
            'username'  => 'tooauto',
            'api_key'   => '059c00197bc410ccc377b9c68aaef51d:H7Jy5xiF3YvYSSopBdf0B6PiMYEcjLRT',
            'sender'    => urlencode($sender),
            'to'        => urlencode($reciever),
            'message'   => urlencode($message),
            'mms'       => $mms,
            'unicode'   => $unicode,
            'media'     => urlencode($media)
        ];

        $url = $apiUrl . '?' . http_build_query($params);
        // dd($url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Erreur cURL : ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }

    /**
     * Envoie un SMS via l'API MTarget en utilisant curl_init
     *
     * @param string $message Le message à envoyer
     * @param string $msisdn Le numéro de téléphone du destinataire (format: +2250758754662)
     * @param string $sender L'expéditeur du SMS (par défaut: TOO AUTO)
     * @return string|false La réponse de l'API ou false en cas d'erreur
     */
    function sendSmsMtarget($message, $msisdn, $sender = 'TOO AUTO')
    {
        return $this->smsService->sendSmsMtarget($message, $msisdn, $sender);
    }

    /**
     * Méthode de test pour envoyer un SMS via l'API MTarget
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testSendSmsMtarget(Request $request)
    {
        try {
            // Paramètres par défaut basés sur votre exemple cURL
            $message = $request->input('message', 'Message test demo');
            $msisdn = $request->input('msisdn', '+2250758754662');
            $sender = $request->input('sender', 'TOO AUTO');

            // Appel de la fonction d'envoi SMS
            $response = $this->smsService->sendSmsMtarget($message, $msisdn, $sender);

            return response()->json([
                'success' => true,
                'message' => 'SMS envoyé avec succès',
                'response' => $response,
                'parameters' => [
                    'message' => $message,
                    'msisdn' => $msisdn,
                    'sender' => $sender
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du SMS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtpForPasswordForget(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'indicatif' => 'required|string',
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Rechercher l'utilisateur avec l'indicatif et le numéro
        $userNumberVerify = User::where('indicatif', $request->indicatif)
                                ->where('mobile', $request->mobile)
                                ->first();

        if (empty($userNumberVerify)) {
            return response()->json([
                'error' => true,
                'message' => 'Numéro de téléphone existe, veuillez vous connecter.',
            ], 404);
        }

        // Générer le code de confirmation
        $confirmationCode = rand(1000, 9999);
        $mobileWithIndicatif = $request->indicatif . $request->mobile;

        // Construire le message
        $message = strtoupper("Votre code de confirmation: " . $confirmationCode);

        // Envoyer le SMS
        try {

            $smsResponse = $this->sendSmsMtarget($message, $mobileWithIndicatif);

            // Enregistrer le code de vérification
            $verifyCode = new Verify_code();
            $verifyCode->code = $confirmationCode;
            $verifyCode->mobile = $mobileWithIndicatif;
            $verifyCode->statut = 0;

            if (!$verifyCode->save()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une erreur est survenue lors de l\'enregistrement du code.',
                ], 500);
            }
        } catch (\Exception $e) {
            // Enregistrer l'erreur dans les logs
            \Log::error('Erreur lors de l\'envoi du SMS : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi du SMS.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Retourner la réponse avec le code de confirmation
        return response()->json([
            'success' => true,
            'message' => 'Code de confirmation envoyé par SMS.',
            'code' => $confirmationCode,
        ], 200);
    }

    public function verifyOtpPasswordForget(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Rechercher l'OTP dans la table Verify_codes
        $verifyCode = Verify_code::where('mobile', $request->mobile)
                                ->where('code', $request->otp)
                                ->where('statut', 0)
                                ->first();

        // Vérifier si l'OTP existe et a le statut 0
        if (empty($verifyCode)) {
            return response()->json([
                'success' => false,
                'message' => 'Le code OTP est invalide ou a déjà été utilisé.',
            ], 404);
        }

        // Mettre à jour le statut de l'OTP pour indiquer qu'il a été utilisé
        $verifyCode->statut = 1;

        if (!$verifyCode->save()) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la validation de l\'OTP.',
            ], 500);
        }

        // Retourner une réponse réussie
        return response()->json([
            'success' => true,
            'message' => 'Le code OTP est valide.',
        ], 200);
    }

    /**
     * Mot de passe oublié
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function passwordForgetUpdate(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric',
            'indicatif' => 'required|numeric',
            'mobile' => 'required|numeric',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6|same:new_password', // Vérification directe
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation des données échouée',
                'errors' => $validator->errors(),
            ], 422);
        }

        $indicatif = $request->indicatif;
        $mobile = $request->mobile;

        // Recherche de l'utilisateur
        $user = User::where(['indicatif' => $indicatif, 'mobile' => $mobile])->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Utilisateur introuvable',
            ], 404); // Code HTTP 404 pour "Non trouvé"
        }

        // Vérification du code de réinitialisation
        $verifyCode = Verify_code::where([
            'mobile' => $indicatif . $mobile,
            'statut' => 1,
            'code' => $request->otp,
        ])->first();

        if (!$verifyCode) {
            return response()->json([
                'status' => false,
                'message' => 'Code de vérification invalide ou expiré',
            ], 400); // Code HTTP 400 pour "Mauvaise requête"
        }

        // Modification du mot de passe dans une transaction
        try {
            DB::beginTransaction();
            $user->password = bcrypt($request->new_password);
            $user->save();

            // Optionnel : Invalider le code de vérification après utilisation
            $verifyCode->statut = 0;
            $verifyCode->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Votre mot de passe a été modifié avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Une erreur est survenue lors de la modification du mot de passe',
                'error' => $e->getMessage(), // À supprimer en production
            ], 500); // Code HTTP 500 pour "Erreur serveur"
        }
    }

}
