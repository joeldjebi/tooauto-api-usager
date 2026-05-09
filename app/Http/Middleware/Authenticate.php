<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Pour toutes les requêtes API, toujours retourner null pour générer une réponse JSON 401
        // au lieu d'essayer de rediriger vers une route de login qui n'existe pas
        $path = $request->path();
        $url = $request->url();
        
        // Vérifier si c'est une route API de plusieurs façons
        if (str_starts_with($path, 'api/') || 
            str_contains($url, '/api/') || 
            $request->is('api/*') ||
            $request->expectsJson()) {
            return null;
        }
        
        // Pour les routes web uniquement, essayer de rediriger vers login
        // Mais seulement si la route existe (pour éviter les erreurs)
        try {
            // Vérifier si la route existe avant de l'appeler
            if (\Route::has('login')) {
                return route('login');
            }
        } catch (\Exception $e) {
            // Si la route login n'existe pas, retourner null
        }
        
        return null;
    }
}