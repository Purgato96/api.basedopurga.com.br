<?php

/**
 * Middleware de segurança para chamadas à API.
 * Valida origens permitidas e adiciona
 * cabeçalhos de proteção às respostas.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica se a requisição vem de um domínio autorizado
        $allowedOrigins = config('cors.allowed_origins', []);
        $origin = $request->header('Origin');

        if ($origin && !in_array($origin, $allowedOrigins) && !$this->matchesPattern($origin)) {
            return response()->json([
                'error' => 'Origem não autorizada',
                'message' => 'Este domínio não tem permissão para acessar a API.'
            ], 403);
        }

        // Adiciona headers de segurança
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }

    /**
     * Verifica se a origem corresponde aos padrões permitidos
     */
    private function matchesPattern(string $origin): bool
    {
        $patterns = config('cors.allowed_origins_patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }
}
