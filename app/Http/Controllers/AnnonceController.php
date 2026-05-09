<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Alert;
use App\Models\Categorie_piece;
use App\Models\Sous_categorie_piece;
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

class AnnonceController extends Controller
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
        $annonces = Annonce::where('usager_id', $user->id)
        ->orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($annonces->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune annonce enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des annonces.',
            'annonces' => $annonces->map(function ($annonce) {
                return $this->attachAnnonceImageUrl($annonce);
            }),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'libelle' => 'required|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'type_de_piece_id' => 'required|exists:type_de_pieces,id',
            'description' => 'nullable|string',
			'modele' => 'nullable|string',
            'marque_id' => 'required|exists:marques,id',
            'mobile' => 'required',
            'is_whatsapp' => 'required|boolean',
            'categorie_piece_id' => 'nullable|exists:categorie_pieces,id',
            'sous_categorie_piece_id' => 'nullable|exists:sous_categorie_pieces,id',
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
    
        DB::beginTransaction();
        try {
            // Création du véhicule
            $annonce = new Annonce();
            $annonce->libelle = $request->libelle;
            $annonce->description = $request->description;
            $annonce->type_de_piece_id = $request->type_de_piece_id;
            $annonce->marque_id = $request->marque_id;
            $annonce->type_etablissement_id = 1;
            $annonce->mobile = $request->mobile;
            $annonce->modele = $request->modele;
            $annonce->is_whatsapp = $request->is_whatsapp;
            $annonce->usager_id = $user->id;
            $annonce->categorie_piece_id = $request->categorie_piece_id;
            $annonce->sous_categorie_piece_id = $request->sous_categorie_piece_id;
            // $annonce->save();
    
            // Sauvegarde des photos
            if ($request->hasFile('image')) {
                $image = $request->file('image');

                $annonce->image = $this->wasabiService->uploadFile(
                    $image,
                    'images/annonce',
                    'annonce'
                );
            }
            
            $annonce->save();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Annonce enregistré avec succès.',
                'annonce' => $this->attachAnnonceImageUrl($annonce),
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement de l'annonce.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'libelle' => 'required|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'modele' => 'nullable|string',
            'type_de_piece_id' => 'required|exists:type_de_pieces,id',
            'marque_id' => 'required|exists:marques,id',
            'annonce_id' => 'required|exists:annonces,id',
            'mobile' => 'required',
            'is_whatsapp' => 'required|boolean',
            'categorie_piece_id' => 'nullable|exists:categorie_pieces,id',
            'sous_categorie_piece_id' => 'nullable|exists:sous_categorie_pieces,id',
        ]);

        $id = $request->annonce_id;
    
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

        $annonce = Annonce::find($id);
    
        if (!$annonce) {
            return response()->json([
                'success' => false,
                'message' => 'Annonce introuvable.',
            ], 404);
        }
    
        DB::beginTransaction();
        try {
            
            $annonce->libelle = $request->libelle;
            $annonce->description = $request->description;
            $annonce->type_de_piece_id = $request->type_de_piece_id;
            $annonce->marque_id = $request->marque_id;
            $annonce->type_etablissement_id = 1;
            $annonce->mobile = $request->mobile;
            $annonce->modele = $request->modele;
            $annonce->is_whatsapp = $request->is_whatsapp;
            $annonce->usager_id = $user->id;
            $annonce->categorie_piece_id = $request->categorie_piece_id;
            $annonce->sous_categorie_piece_id = $request->sous_categorie_piece_id;
            // $annonce->save();
    
            // Sauvegarde des photos
            if ($request->hasFile('image')) {
                $image = $request->file('image');

                if ($annonce->image) {
                    $this->wasabiService->deleteFile(
                        $this->normalizeAnnonceImagePath($annonce->image)
                    );
                }

                $annonce->image = $this->wasabiService->uploadFile(
                    $image,
                    'images/annonce',
                    'annonce'
                );
            }
            
            $annonce->save();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Annonce mise a jour avec succès.',
                'annonce' => $this->attachAnnonceImageUrl($annonce),
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la mise a jour.",
                'dev' => $e->getMessage(),
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
            'annonce_id' => 'required|exists:annonces,id',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        $id = $request->annonce_id;
    
        // Récupérer le véhicule
        $annonce = Annonce::find($id);
        if (!$annonce) {
            return response()->json([
                'success' => false,
                'message' => 'Annonce introuvable.',
            ], 404);
        }
    
        DB::beginTransaction();
        try {

            if ($annonce->image) {
                $this->wasabiService->deleteFile(
                    $this->normalizeAnnonceImagePath($annonce->image)
                );
            }
    
            // Supprimer les alertes liées (si nécessaire)
            Alert::where('id', $id)->delete();
    
            // Supprimer le véhicule
            $annonce->delete();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Annonce supprimé avec succès.',
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la suppression de l'annonce.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    protected function attachAnnonceImageUrl($annonce)
    {
        if (!$annonce || empty($annonce->image)) {
            return $annonce;
        }

        $path = $this->normalizeAnnonceImagePath($annonce->image);

        try {
            $annonce->image = $this->wasabiService->temporaryUrl($path) ?? $annonce->image;
        } catch (\Throwable $e) {
            $annonce->image = $path;
        }

        return $annonce;
    }

    protected function normalizeAnnonceImagePath($image)
    {
        if (empty($image)) {
            return $image;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        if (Str::contains($image, '/')) {
            return $image;
        }

        return 'images/annonce/' . ltrim($image, '/');
    }
	
	
    /**
     * Display a listing of the resource.
     */
    public function getCategoriePiece()
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
        $categorie_pieces = Categorie_piece::orderBy('id', 'desc')->get();

        // Vérifier si des établissements existent
        if ($categorie_pieces->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune catégorie de pièce enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des categories pieces.',
            'categorie_pieces' => $categorie_pieces,
        ], 200);
    }

        /**
     * Store a newly created resource in storage.
     */
    public function getSousCategoriePiece(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'categorie_piece_id' => 'required|exists:categorie_pieces,id',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        // Récupérer l'établissement avec ses relations
        $souscategoriepiece = Sous_categorie_piece::where('categorie_piece_id', $request->categorie_piece_id)
            ->with(['categorie_piece'])
            ->get();
    
        // Vérifier si l'établissement est nul
        if (!$souscategoriepiece) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun sous catégorie trouvé avec cet ID.',
            ], 404);
        }
    
        // Retourner les détails de l'établissement
        return response()->json([
            'success' => true,
            'message' => 'Liste des sous categories.',
            'souscategoriepieces' => $souscategoriepiece,
        ], 200);
    }
}
