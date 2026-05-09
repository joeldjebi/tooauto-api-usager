<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Vehicule;
use App\Models\Tuto;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Categorie_tuto;
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

class TutoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        // Vérifier si des établissements existent
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Récupérer les Tuto triés par ID décroissant
        $tutos = Tuto::orderBy('id', 'desc')
		->with('categorie_tuto')
        ->get();

        // Vérifier si des infractions existent
        if ($tutos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun tuto enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des tutos
        return response()->json([
            'success' => true,
            'message' => 'Liste des tutos.',
            'tutos' => $tutos,
        ], 200);
    }
	
	
}