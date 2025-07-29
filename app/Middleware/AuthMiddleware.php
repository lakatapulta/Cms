<?php

namespace FlexCMS\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthMiddleware
{
    /**
     * Handle authentication
     */
    public function handle(Request $request, \Closure $next)
    {
        $auth = app('auth');
        
        if (!$auth->check()) {
            // Si es una petición AJAX, devolver JSON
            if ($request->isXmlHttpRequest() || $request->headers->get('Content-Type') === 'application/json') {
                return new Response(
                    json_encode(['error' => 'Unauthenticated', 'redirect' => '/login']),
                    401,
                    ['Content-Type' => 'application/json']
                );
            }
            
            // Guardar URL intentada para redirección después del login
            $_SESSION['intended_url'] = $request->getUri();
            
            return new RedirectResponse('/login');
        }
        
        return $next($request);
    }
}