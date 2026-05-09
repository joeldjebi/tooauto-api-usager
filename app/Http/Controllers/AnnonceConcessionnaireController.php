<?php

namespace App\Http\Controllers;

use App\Models\Annonce_concessionnaire;
use App\Models\Concessionnaire;
use App\Models\Type_de_piece;
use App\Models\Type_de_vehicule;
use App\Models\Type_de_demande;
use App\Models\Marque;
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

class AnnonceConcessionnaireController extends Controller
{
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

        $annonces = Annonce_concessionnaire::where('statut', 1)
        ->where('user_id', $user->id)
        ->with('marque', 'type_de_demande', 'type_de_vehicule')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $annonces
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validation des données d'entrée avec messages personnalisés
            $validator = Validator::make($request->all(), [
                'type_de_demande_id' => 'required|integer|exists:type_de_demandes,id',
                'type_de_vehicule_id' => 'required|integer|exists:type_de_vehicules,id',
                'marque_id' => 'required|integer|exists:marques,id',
                'modele' => 'required|string|max:200',
                'user_id' => 'required|integer|exists:users,id',
                'concessionaire_id' => 'required|integer|exists:concessionnaires,id',
            ], [
                'type_de_demande_id.required' => 'Le type de demande est obligatoire.',
                'type_de_demande_id.exists' => 'Le type de demande sélectionné n\'existe pas.',
                'type_de_vehicule_id.required' => 'Le type de véhicule est obligatoire.',
                'type_de_vehicule_id.exists' => 'Le type de véhicule sélectionné n\'existe pas.',
                'marque_id.required' => 'La marque est obligatoire.',
                'marque_id.exists' => 'La marque sélectionnée n\'existe pas.',
                'modele.required' => 'Le modèle est obligatoire.',
                'modele.max' => 'Le modèle ne doit pas dépasser 200 caractères.',
                'user_id.required' => 'L\'utilisateur est obligatoire.',
                'user_id.exists' => 'L\'utilisateur sélectionné n\'existe pas.',
                'concessionaire_id.required' => 'Le concessionnaire est obligatoire.',
                'concessionaire_id.exists' => 'Le concessionnaire sélectionné n\'existe pas.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation incorrectes.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // Vérifier si l'utilisateur a le droit de créer une annonce pour ce concessionnaire
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié.',
                ], 401);
            }

            // Ajouter l'utilisateur connecté si user_id n'est pas fourni ou différent
            if (!isset($validated['user_id']) || $validated['user_id'] != $user->id) {
                $validated['user_id'] = $user->id;
            }

            // Créer l'annonce
            $annonce = Annonce_concessionnaire::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Annonce créée avec succès.',
                'data' => $annonce
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'annonce en base de données.',
                'error' => 'Erreur de base de données'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue s\'est produite.',
                'error' => 'Erreur interne du serveur'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'type_de_demande_id' => 'sometimes|exists:type_de_demandes,id',
            'type_de_vehicule_id' => 'sometimes|exists:type_de_vehicules,id',
            'concessionaire_id', //=> 'sometimes|exists:concessionaires,id',
            'marque_id' => 'sometimes|exists:marques,id',
            'modele' => 'sometimes|string|max:200',
        ]);

        $annonce = Annonce_concessionnaire::find($id);

        if (!$annonce || $annonce->statut == 0) {
            return response()->json(['success' => false, 'message' => 'Annonce introuvable.'], 404);
        }

        $annonce->update($validated);

        return response()->json(['success' => true, 'message' => 'Annonce mise à jour avec succès.', 'data' => $annonce]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $annonce = Annonce_concessionnaire::find($id);

        if (!$annonce || $annonce->statut == 0) {
            return response()->json(['success' => false, 'message' => 'Annonce introuvable ou déjà désactivée.'], 404);
        }

        $annonce->update(['statut' => 0]);

        return response()->json(['success' => true, 'message' => 'Annonce désactivée avec succès.']);
    }
}