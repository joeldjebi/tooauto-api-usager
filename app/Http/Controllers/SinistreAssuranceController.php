<?php

namespace App\Http\Controllers;

use App\Models\SinistreAssurance;
use App\Services\WasabiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SinistreAssuranceController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    /**
     * Signaler un sinistre à une entreprise d'assurance.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entreprise_assurance_id' => 'required|exists:entreprises_assurances,id',
            'user_id' => 'required|exists:users,id',
            'immatriculation_vehicule' => 'required|string|max:100',
            'numero_police_assurance' => 'required|string|max:150',
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
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
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        if (!$request->hasFile('photos')) {
            return response()->json([
                'success' => false,
                'message' => 'Le champ photos est requis.',
            ], 422);
        }

        $photosPaths = [];

        DB::beginTransaction();
        try {
            foreach ($request->file('photos') as $photo) {
                $photosPaths[] = $this->wasabiService->uploadFile(
                    $photo,
                    'sinistres-assurances/photos',
                    'sinistre-assurance'
                );
            }

            $sinistre = SinistreAssurance::create([
                'entreprise_assurance_id' => $request->entreprise_assurance_id,
                'user_id' => $request->user_id,
                'immatriculation_vehicule' => $request->immatriculation_vehicule,
                'numero_police_assurance' => $request->numero_police_assurance,
                'photos' => $photosPaths,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sinistre signalé avec succès.',
                'sinistre' => $sinistre,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($photosPaths as $photoPath) {
                $this->wasabiService->deleteFile($photoPath);
            }

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors du signalement du sinistre.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retourne les sinistres signalés par un utilisateur.
     */
    public function getByUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
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
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $sinistres = SinistreAssurance::where('user_id', $request->user_id)
            ->with('entrepriseAssurance', 'user')
            ->orderBy('id', 'desc')
            ->get();

        if ($sinistres->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun sinistre enregistré pour cet utilisateur.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des sinistres de l\'utilisateur.',
            'sinistres' => $sinistres->map(fn ($sinistre) => $this->attachPhotoUrls($sinistre)),
        ], 200);
    }

    /**
     * Retourne les sinistres signalés à une entreprise d'assurance.
     */
    public function getByEntrepriseAssurance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entreprise_assurance_id' => 'required|exists:entreprises_assurances,id',
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
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $sinistres = SinistreAssurance::where('entreprise_assurance_id', $request->entreprise_assurance_id)
            ->with('entrepriseAssurance', 'user')
            ->orderBy('id', 'desc')
            ->get();

        if ($sinistres->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun sinistre enregistré pour cette entreprise d'assurance.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Liste des sinistres de l'entreprise d'assurance.",
            'sinistres' => $sinistres->map(fn ($sinistre) => $this->attachPhotoUrls($sinistre)),
        ], 200);
    }

    private function attachPhotoUrls(SinistreAssurance $sinistre): SinistreAssurance
    {
        $photos = is_array($sinistre->photos) ? $sinistre->photos : [];

        $sinistre->photo_urls = array_values(array_filter(array_map(function ($photo) {
            if (empty($photo)) {
                return null;
            }

            if (filter_var($photo, FILTER_VALIDATE_URL)) {
                return $photo;
            }

            try {
                return $this->wasabiService->temporaryUrl($photo) ?? $photo;
            } catch (\Throwable $e) {
                return $photo;
            }
        }, $photos)));

        return $sinistre;
    }
}
