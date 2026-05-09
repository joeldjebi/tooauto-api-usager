<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Vehicule;
use App\Models\Info;
use App\Models\Commissariat_inofs;
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

class InfosController extends Controller
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

        // Récupérer les infos triés par ID décroissant
        $infos = Info::orderBy('id', 'desc')->get();

        // Vérifier si des infos existent
        if ($infos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun info enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des tutos
        return response()->json([
            'success' => true,
            'message' => 'Liste des infos.',
            'infos' => $infos,
        ], 200);
    }

    /**
     * Récupérer les commissariat_inofs par catégorie.
     */
    public function getCommissariatInofsByCategorie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categorie' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
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

        $commissariatInofs = Commissariat_inofs::where('categorie', $request->categorie)
            ->where('statut', 1)
            ->orderBy('commune', 'asc')
            ->orderBy('nom', 'asc')
            ->get();

        if ($commissariatInofs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun commissariat info enregistré pour cette catégorie.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des commissariat infos.',
            'commissariat_inofs' => $commissariatInofs,
        ], 200);
    }
	
	
}
