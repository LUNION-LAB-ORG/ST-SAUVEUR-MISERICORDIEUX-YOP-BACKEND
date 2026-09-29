<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse toute requête d'un compte désactivé (défense en profondeur : ses jetons sont aussi révoqués).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDisabled()) {
            $user->currentAccessToken()?->delete();
            return response()->json(['error' => 'Ce compte est désactivé.'], 403);
        }

        return $next($request);
    }
}
