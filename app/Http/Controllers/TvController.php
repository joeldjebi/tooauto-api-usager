<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Tv;
use App\Models\Categorie_tv;
use App\Models\Info;
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

class TvController extends Controller
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

        // Récupérer les tvs triés par ID décroissant
        $tvs = Tv::orderBy('id', 'desc')->get();

        // Vérifier si des infos existent
        if ($tvs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun tv enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des tutos
        return response()->json([
            'success' => true,
            'message' => 'Liste des tvs.',
            'tvs' => $tvs,
        ], 200);
    }
	
	
}