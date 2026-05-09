<?php

namespace App\Http\Controllers;

use App\Models\Rdv_concessionnaire;
use App\Models\Concessionnaire;
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

class RdvConcessionnaireController extends Controller
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

        $rdvs = Rdv_concessionnaire::where('user_id', $user->id)
            ->with('concessionnaire')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rdvs
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'jour' => 'required|string|max:30',
                'heure' => 'required|string|max:20',
                'concessionnaire_id' => 'required',  // pas besoin de la validation exists
                'user_id' => 'required|exists:users,id',
            ]);

            $rdv = Rdv_concessionnaire::create($validated);

            return response()->json([
                'success' => true,
                'data' => $rdv
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422); // Code de statut HTTP 422 pour une erreur de validation
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
                'error' => $e->getMessage(),
            ], 500); // Code de statut HTTP 500 pour une erreur serveur
        }
    }


    /**
     * Display the specified resource.
     */
    public function update(Request $request, $id)
    {
        $rdv = Rdv_concessionnaire::find($id);

        if (!$rdv) {
            return response()->json(['success' => false, 'message' => 'Rendez-vous introuvable.'], 404);
        }

        $validated = $request->validate([
            'jour' => 'required|string|max:30',
            'heure' => 'required|string|max:20',
            'concessionnaire_id' => 'required',
            'user_id' => 'required|exists:users,id',
        ]);

        $rdv->update($validated);

        return response()->json(['success' => true, 'message' => 'Rendez-vous mis à jour avec succès.', 'data' => $rdv]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $rdv = RdvConcessionnaire::find($id);

        if (!$rdv) {
            return response()->json(['success' => false, 'message' => 'Rendez-vous introuvable.'], 404);
        }

        // Changer le statut à 0 au lieu de supprimer l'enregistrement
        $rdv->update(['statut' => 0]);

        return response()->json(['success' => true, 'message' => 'Rendez-vous annulé avec succès.']);
    }
}
