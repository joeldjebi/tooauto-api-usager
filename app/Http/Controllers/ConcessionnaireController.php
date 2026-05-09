<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Vehicule;
use App\Models\Concessionnaire;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Userconcessionnaire;
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

class ConcessionnaireController extends Controller
{
    protected $wasabiService;

    public function __construct(WasabiService $wasabiService)
    {
        $this->wasabiService = $wasabiService;
    }

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

        // Récupérer les établissements triés par ID décroissant
        $concessionnaires = Concessionnaire::orderBy('id', 'desc')
		->with('userconcessionnaire')
        ->get();

        // Vérifier si des établissements existent
        if ($concessionnaires->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun concessionnaire enregistré pour le moment.',
            ], 404);
        }

        // Retourner la liste des établissements
        return response()->json([
            'success' => true,
            'message' => 'Liste des concessionnaires.',
            'concessionnaires' => $concessionnaires->map(function ($concessionnaire) {
                return $this->attachConcessionnaireImageUrls($concessionnaire);
            }),
        ], 200);
    }

    protected function attachConcessionnaireImageUrls($concessionnaire)
    {
        if (!$concessionnaire) {
            return $concessionnaire;
        }

        $logoUrl = $this->concessionnaireImageUrl($concessionnaire->logo ?? null, 'logo');
        $coverUrl = $this->concessionnaireImageUrl($concessionnaire->cover ?? null, 'cover');

        $concessionnaire->logo = $logoUrl;
        $concessionnaire->cover = $coverUrl;
        $concessionnaire->logo_url = $logoUrl;
        $concessionnaire->cover_url = $coverUrl;

        return $concessionnaire;
    }

    protected function concessionnaireImageUrl(?string $image, string $type): ?string
    {
        if (empty($image)) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $path = $this->normalizeConcessionnaireImagePath($image, $type);

        try {
            return $this->wasabiService->temporaryUrl($path) ?? $this->wasabiPublicUrl($path);
        } catch (\Throwable $e) {
            if (Str::contains($image, '/')) {
                return $this->wasabiPublicUrl($path);
            }

            return asset('concessionnaire/' . $type . '/' . ltrim($image, '/'));
        }
    }

    protected function normalizeConcessionnaireImagePath(string $image, string $type): string
    {
        if (Str::contains($image, '/')) {
            return ltrim($image, '/');
        }

        return 'concessionnaire/' . $type . '/' . ltrim($image, '/');
    }

    protected function wasabiPublicUrl(string $path): string
    {
        return rtrim((string) config('wasabi.url'), '/') . '/' . ltrim($path, '/');
    }
	
	
}
