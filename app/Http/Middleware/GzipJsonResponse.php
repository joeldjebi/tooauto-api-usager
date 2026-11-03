<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GzipJsonResponse
{
    // En dessous de cette taille, la compression ne fait rien gagner
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || !function_exists('gzencode')
            || $response->headers->has('Content-Encoding')
            || !str_contains((string) $request->header('Accept-Encoding'), 'gzip')
            || !str_contains((string) $response->headers->get('Content-Type'), 'json')
        ) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || strlen($content) < self::MIN_BYTES) {
            return $response;
        }

        $compressed = gzencode($content, 6);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->setVary('Accept-Encoding', false);

        return $response;
    }
}
