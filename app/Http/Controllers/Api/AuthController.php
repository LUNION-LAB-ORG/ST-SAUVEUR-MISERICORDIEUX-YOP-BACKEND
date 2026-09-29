<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user and return token
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);

        // Sécurité : l'inscription publique ne donne jamais accès au back-office.
        // Seul le tout premier compte (base vide) est créé administrateur actif ;
        // les suivants sont créés désactivés, avec le rôle le plus restreint, en attente
        // d'activation (et d'attribution de rôle) par un administrateur.
        $bootstrap = !User::query()->withTrashed()->exists();
        // Rôle aux droits les plus restreints (aucune écriture sans mouvement attribué)
        $data['role']   = $bootstrap ? 'admin' : 'movement_leader';
        $data['status'] = $bootstrap ? 'active' : 'inactive';

        $user = User::create($data);

        if (!$bootstrap) {
            return (new UserResource($user))
                ->additional(['message' => 'Compte créé. Il doit être activé par un administrateur avant la première connexion.'])
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return (new UserResource($user))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Login user and return token
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'phone', 'password');

        $user = null;
        if (!empty($credentials['email'])) {
            $user = User::where('email', $credentials['email'])->first();
        } elseif (!empty($credentials['phone'])) {
            $user = User::where('phone', $credentials['phone'])->first();
        }

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants invalides'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isDisabled()) {
            return response()->json([
                'error' => 'Ce compte est désactivé. Contactez un administrateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $token = $user->createToken('api_token')->plainTextToken;

        return (new UserResource($user))
            ->additional(['token' => $token]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Déconnecté avec succès'
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('api_token')->plainTextToken;

        return (new UserResource($user))
            ->additional(['token' => $token]);
    }
}
