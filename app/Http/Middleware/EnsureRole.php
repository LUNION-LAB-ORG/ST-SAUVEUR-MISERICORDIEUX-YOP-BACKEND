<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès par rôle : `role:secretariat,communication`. L'admin passe partout.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->hasRole(...$roles)) {
            return self::forbidden();
        }

        return $next($request);
    }

    public static function forbidden(): Response
    {
        return response()->json(['error' => 'Accès refusé pour votre rôle.'], 403);
    }
}
