<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Récupérer la clé API depuis le header ou le paramètre
        $apiKey = $request->header('X-API-Key') 
            ?? $request->header('Authorization') 
            ?? $request->input('api_key');

        // Si Authorization header est au format "Bearer {key}", extraire la clé
        if ($request->header('Authorization') && str_starts_with($request->header('Authorization'), 'Bearer ')) {
            $apiKey = substr($request->header('Authorization'), 7);
        }

        $expectedKey = env('CRON_API_KEY');

        // Vérifier si la clé API est configurée
        if (empty($expectedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API non configurée côté serveur.',
            ], 500);
        }

        // Vérifier si la clé API est fournie
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API manquante. Fournissez-la via le header X-API-Key ou le paramètre api_key.',
            ], 401);
        }

        // Vérifier si la clé API est valide
        if ($apiKey !== $expectedKey) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API invalide.',
            ], 401);
        }

        return $next($request);
    }
}
