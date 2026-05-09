<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Models\Forfait_usager;
use App\Models\Vehicule;
use App\Models\Forfait;
use App\Models\AbonnementUsager;
use App\Models\Incident;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Constat;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
	public function storePaiement(Request $request)
	{
		$request->validate([
			'referenceNumber' => 'required|string|unique:paiements',
			'amount' => 'required|string',
			'description' => 'nullable|string',
			'countryCurrencyCode' => 'nullable|string',
			'customerEmail' => 'nullable|email',
			'customerFirstName' => 'required|string',
			'customerLastname' => 'required|string',
			'customerPhoneNumber' => 'required|string',
			'user_id' => 'required|exists:users,id',
			'forfait_id' => 'required|exists:forfait_usagers,id'
		]);

		try {
			$paiement = Paiement::create([
				'referenceNumber' => $request->referenceNumber,
				'amount' => $request->amount,
				'description' => $request->description,
				'countryCurrencyCode' => $request->countryCurrencyCode,
				'customerEmail' => $request->customerEmail,
				'customerFirstName' => $request->customerFirstName,
				'customerLastname' => $request->customerLastname,
				'customerPhoneNumber' => $request->customerPhoneNumber,
				'user_id' => $request->user_id,
				'forfait_id' => $request->forfait_id,
				'statut' => 'en_attente'
			]);

			return response()->json([
				'status' => 'success',
				'message' => 'Paiement enregistré avec succès',
				'data' => $paiement
			], 201);

		} catch (\Exception $e) {
			return response()->json([
				'status' => 'error',
				'message' => 'Erreur lors de l\'enregistrement du paiement',
				'error' => $e->getMessage()
			], 500);
		}
	}
	
		public function storeAbonnementGratuit(Request $request)
	{
		// Validation
		$data = $request->validate([
			'user_id'    => 'required|exists:users,id',
			'forfait_id' => 'required|exists:forfait_usagers,id',
		]);

		try {
			// Récupérer le forfait
			$forfait = Forfait_usager::findOrFail($data['forfait_id']);
			// Vérifier que le forfait est bien gratuit
			if (strtolower(trim($forfait->libelle)) !== 'freemium') {
				return response()->json([
					'status'  => 'error',
					'message' => 'Ce forfait n’est pas gratuit (FREEMIUM)',
				], 400);
			}

			// Vérifier si l’utilisateur a déjà bénéficié d’un abonnement gratuit
			$aDejaUnGratuit = AbonnementUsager::where('user_id', $data['user_id'])
				->where('is_free', 1)
				->exists();

			if ($aDejaUnGratuit) {
				return response()->json([
					'status'  => 'error',
					'message' => 'Vous avez déjà utilisé votre abonnement gratuit',
				], 400);
			}

			// Vérifier s’il existe un abonnement actif
			$abonnementActif = AbonnementUsager::where('user_id', $data['user_id'])
				->where('statut', 1)
				->where('date_fin', '>', now())
				->exists();

			if ($abonnementActif) {
				return response()->json([
					'status'  => 'error',
					'message' => 'L’utilisateur a déjà un abonnement actif',
				], 400);
			}

			// Dates
			$dateDebut = now();
			$dateFin   = now()->addMonths((int) $forfait->duree);

			// Création de l’abonnement
			$abonnement = AbonnementUsager::create([
				'user_id'    => $data['user_id'],
				'forfait_id' => $forfait->id,
				'date_debut' => $dateDebut,
				'date_fin'   => $dateFin,
				'statut'     => 1,
				'is_free'    => 1,
			]);

			return response()->json([
				'status'  => 'success',
				'message' => 'Abonnement gratuit enregistré avec succès',
				'data'    => [
					'abonnement' => $abonnement,
					'date_debut' => $dateDebut->toDateString(),
					'date_fin'   => $dateFin->toDateString(),
				],
			], 201);

		} catch (\Throwable $e) {
			return response()->json([
				'status'  => 'error',
				'message' => 'Erreur lors de l’enregistrement de l’abonnement',
				'error'   => $e->getMessage(),
			], 500);
		}
	}


	
	public function verifierStatutPaiementApi(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'reference' => 'required|string',
			'forfait_id' => 'required|integer|exists:forfait_usagers,id',
			'user_id' => 'required|integer|exists:users,id',
		]);

		$reference = $validated['reference'];
		$forfaitId = $validated['forfait_id'];
		$userId = $validated['user_id'];

		try {
			$forfait = Forfait_usager::findOrFail($forfaitId);
			if ($forfait->id == 1) {
				return response()->json([
					'success' => false,
					'message' => 'Ce forfait est invalide pour un abonnement.',
				], 400);
			}

			$paiement = Paiement::where([
				'referenceNumber' => $reference,
				'amount' => intval($forfait->prix),
				'user_id' => $userId,
			])->whereIn('statut', ['en_attente', 'failed'])->first();

			if (!$paiement) {
				return response()->json([
					'success' => false,
					'message' => 'Aucun paiement en attente trouvé avec ces informations.',
				], 404);
			}

			// Appel de l'API PaiementPro
			$response = Http::withHeaders(['Content-Type' => 'application/json'])
				->post("https://api.paiementpro.net/status/{$reference}");

			if (!$response->ok()) {
				return response()->json([
					'success' => false,
					'message' => 'Erreur de communication avec l’API PaiementPro.',
					'http_status' => $response->status(),
				], 502);
			}

			$result = $response->json();

			if (!isset($result['success'])) {
				return response()->json([
					'success' => false,
					'message' => 'Réponse inattendue de l’API PaiementPro.',
					'data' => $result,
				], 500);
			}

			if ($result['success'] === true) {
				DB::beginTransaction();

				try {
					$dateDebut = now();
					$dateFin = now()->addMonths((int) $forfait->duree);

					$abonnement = AbonnementUsager::create([
						'user_id' => $userId,
						'forfait_id' => $forfaitId,
						'date_debut' => $dateDebut,
						'date_fin' => $dateFin,
					]);

					$paiement->update([
						'statut' => 'success',
						'date_debut' => $dateDebut,
						'date_fin' => $dateFin,
						'reponse_api' => $result,
					]);

					DB::commit();

					return response()->json([
						'success' => true,
						'message' => 'Paiement confirmé avec succès. Abonnement activé.',
						'data' => [
							'paiement' => $paiement,
							'abonnement' => $abonnement,
							'api' => $result,
						],
					]);
				} catch (\Exception $e) {
					DB::rollBack();

					Log::error('Échec lors de la création de l’abonnement après un paiement réussi.', [
						'reference' => $reference,
						'error' => $e->getMessage(),
					]);

					return response()->json([
						'success' => false,
						'message' => 'Le paiement a réussi mais l’abonnement n’a pas pu être enregistré.',
					], 500);
				}
			}

			// Cas d’échec : success = false
			$paiement->update([
				'statut' => 'failed',
				'reponse_api' => $result,
			]);

			return response()->json([
				'success' => false,
				'message' => $result['error'] ?? 'Le paiement a échoué.',
				'data' => $result,
			], 402);

		} catch (\Exception $e) {
			Log::error('Erreur système lors de la vérification du paiement.', [
				'reference' => $reference ?? null,
				'forfait_id' => $forfaitId ?? null,
				'user_id' => $userId ?? null,
				'message' => $e->getMessage(),
			]);

			return response()->json([
				'success' => false,
				'message' => 'Erreur interne : ' . $e->getMessage(),
			], 500);
		}
	}
	
	public function verifierStatutPaiement($reference, $forfaitId, $userId)
	{
		try {
			// Validation des paramètres
			if (empty($reference) || empty($forfaitId) || empty($userId)) {
				throw new \Exception('La référence, l\'ID du forfait et l\'ID de l\'utilisateur sont requis');
			}

			// Vérification de l'existence du promoteur
			$promoteur = User::find($userId);
			if (!$promoteur) {
				throw new \Exception('Promoteur non trouvé');
			}

			// Vérification de l'existence du forfait
			$forfait = Forfait::find($forfaitId);
			if (!$forfait || $forfait->id == 1) {
				throw new \Exception('Forfait non trouvé');
			}
			
			

			// Vérification de l'existence du paiement
			/*$paiement = Paiement::where([
				'referenceNumber' => $reference,
				'amount' => intval($forfait->prix),
				'statut' => 'en_attente',
				'user_id' => $userId
			])->first();*/
			$paiement = Paiement::where([
				'referenceNumber' => $reference,
				'amount' => intval($forfait->prix),
				'user_id' => $userId,
			])->whereIn('statut', ['en_attente', 'failed'])->first();

			if (!$paiement) {
				throw new \Exception('Paiement non trouvé ou déjà traité');
			}

			// Appel à l'API PaiementPro
			$url = "https://api.paiementpro.net/status/" . $reference;

			$curl = curl_init();

			curl_setopt_array($curl, [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
				CURLOPT_POSTFIELDS => '', // Corps de la requête vide comme dans votre curl
			]);

			$response = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			if (curl_errno($curl)) {
				throw new \Exception('Erreur Curl: ' . curl_error($curl));
			}

			curl_close($curl);

			$result = json_decode($response, true);

			// Vérification de la réponse
			if (!isset($result['success'])) {
				throw new \Exception('Réponse invalide de l\'API');
			}

			// Traitement du paiement réussi
			if ($result['success'] === true) {
				\DB::beginTransaction();
				try {
					// Création de l'abonnement
					$abonnement = new AbonnementUsager();
					$abonnement->user_id = $userId;
					$abonnement->forfait_id = $forfaitId;
					$abonnement->date_debut = now();
					$abonnement->date_fin = now()->addDays($forfait->duree);
					$abonnement->save();

					// Mise à jour du paiement
					$paiement->update([
						'statut' => 'success',
						'date_debut' => now(),
						'date_fin' => now()->addDays($forfait->duree),
						'reponse_api' => $result
					]);

					\DB::commit();

					return [
						'success' => true,
						'message' => 'Paiement traité avec succès',
						'data' => [
							'paiement' => $paiement,
							'abonnement' => $abonnement
						]
					];
				} catch (\Exception $e) {
					\DB::rollBack();
					throw new \Exception('Erreur lors du traitement du paiement: ' . $e->getMessage());
				}
			}

			// Paiement échoué
			$paiement->update([
				'statut' => 'failed',
				'reponse_api' => $result
			]);

			return [
				'success' => false,
				'message' => $result['error'] ?? 'Le paiement a échoué',
				'data' => $result
			];

		} catch (\Exception $e) {
			\Log::error('Erreur lors de la vérification du statut du paiement', [
				'message' => $e->getMessage(),
				'reference' => $reference,
				'forfait_id' => $forfaitId,
				'user_id' => $userId ?? null
			]);

			return [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
	}
	
	
	    
	
	public function pageVerification($reference, $forfaitId, $userId)
    {
        try {
            $resultat = $this->verifierStatutPaiement($reference, $forfaitId, $userId);

            return view('paiements.verification', [
                'statut' => $resultat['success'],
                'message' => $resultat['message'],
                'data' => $resultat['data'] ?? null,
                'reference' => $reference
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur dans pageVerification', [
                'message' => $e->getMessage(),
                'reference' => $reference,
                'forfait_id' => $forfaitId,
                'user_id' => $userId
            ]);

            return view('paiements.verification', [
                'statut' => false,
                'message' => $e->getMessage(),
                'reference' => $reference
            ]);
        }
    }
	
	
	public function retryPayment($reference)
	{
		try {
			// Récupérer le paiement
			$paiement = Paiement::where('reference_number', $reference)
				->where('promoteur_id', Auth::user()->id)
				->first();

			if (!$paiement) {
				throw new \Exception('Paiement non trouvé');
			}

			// Rediriger vers la page de paiement
			return redirect()->route('paiement.initier', [
				'montant' => $paiement->amount,
				'forfait_id' => $paiement->forfait_id
			]);

		} catch (\Exception $e) {
			return redirect()->back()->with('error', $e->getMessage());
		}
	}
	
	
	
	
	
	
	
	
	
	
	
	
}