<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticateMultiple
{
    protected $guards = ['api', 'chauffeur']; // liste des guards à vérifier

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token manquant.',
            ], 401);
        }

        // Essayer d'authentifier avec chaque guard
        foreach ($this->guards as $guard) {
            try {
                // Essayer d'authentifier l'utilisateur avec ce guard
                // Auth::guard($guard)->user() lit automatiquement le token depuis la requête
                // et vérifie si le token correspond au provider de ce guard
                $user = Auth::guard($guard)->user();
                
                if ($user) {
                    // Authentification réussie, définir le guard et continuer
                    Auth::shouldUse($guard);
                    return $next($request);
                }
            } catch (TokenExpiredException $e) {
                // Token expiré, continuer avec le prochain guard
                continue;
            } catch (TokenInvalidException $e) {
                // Token invalide pour ce guard (probablement mauvais provider),
                // continuer avec le prochain guard
                continue;
            } catch (JWTException $e) {
                // Autre erreur JWT, continuer avec le prochain guard
                continue;
            } catch (\Exception $e) {
                // Autre exception, continuer avec le prochain guard
                continue;
            }
        }

        // Aucun guard n'a réussi à authentifier l'utilisateur
        return response()->json([
            'success' => false,
            'message' => 'Non authentifié.',
        ], 401);
    }
}