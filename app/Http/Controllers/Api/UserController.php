<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    protected UserRepositoryInterface $repo;

    public function __construct(UserRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List users (paginated)
     */
    public function index(Request $request)
    {
        $conditions = [];

        if ($request->filled('fullname')) {
            $conditions[] = ['fullname', 'LIKE', '%' . $request->fullname . '%'];
        }

        if ($request->filled('email')) {
            $conditions[] = ['email', 'LIKE', '%' . $request->email . '%'];
        }

        if ($request->filled('phone')) {
            $conditions[] = ['phone', 'LIKE', '%' . $request->phone . '%'];
        }

        if ($request->filled('status')) {
            $conditions[] = ['status', '=', $request->status];
        }

        if ($request->filled('role')) {
            $conditions[] = ['role', '=', $request->role];
        }

        $users = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return UserResource::collection($users);
    }

    /**
     * Store a new user
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        // Rôle non fourni : valeur par défaut de la colonne (comportement historique)
        if (array_key_exists('role', $data) && $data['role'] === null) {
            unset($data['role']);
        }
        // Valeur historique « inactive » conservée ; « disabled » accepté comme synonyme
        if (($data['status'] ?? null) === 'disabled') {
            $data['status'] = 'inactive';
        }

        // Hash password
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        // Upload photo
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('users', 'public');
            $data['photo'] = 'storage/' . $path;
        }

        $user = $this->repo->create($data);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show user details
     */
    public function show(string $id)
    {
        return new UserResource($this->repo->find($id));
    }

    /**
     * Update user
     */
    public function update(UpdateRequest $request, string $id)
    {
        $data = $request->validated();

        // Hash password if present (et non vide)
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $existing = $this->repo->find($id);

        if (array_key_exists('role', $data) && $data['role'] === null) {
            unset($data['role']);
        }
        // Valeur historique « inactive » conservée ; « disabled » accepté comme synonyme
        if (($data['status'] ?? null) === 'disabled') {
            $data['status'] = 'inactive';
        }

        // Garde-fous : pas d'auto-désactivation, jamais sans administrateur actif
        if ($error = $this->guardAdminChange($request->user(), $existing, $data)) {
            return response()->json(['error' => $error], 422);
        }

        // Upload photo
        if ($request->hasFile('photo')) {
            if ($existing && $existing->photo) {
                $old = preg_replace('#^storage/#', '', $existing->photo);
                if (Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }
            $path = $request->file('photo')->store('users', 'public');
            $data['photo'] = 'storage/' . $path;
        }

        $user = $this->repo->update($id, $data);

        // Compte désactivé : tous ses jetons sont révoqués
        if ($user->isDisabled()) {
            $user->tokens()->delete();
        }

        return new UserResource($user);
    }

    /**
     * Endpoint /me — récupère le profil de l'utilisateur connecté
     */
    public function me(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }
        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * Endpoint PUT /me — met à jour le profil de l'utilisateur connecté
     */
    public function updateMe(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        $data = $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'email'    => 'sometimes|nullable|email|max:100|unique:users,email,' . $user->id,
            'phone'    => 'sometimes|string|max:100|unique:users,phone,' . $user->id,
            'password' => 'sometimes|nullable|string|min:6',
            'photo'    => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('photo')) {
            if ($user->photo) {
                $old = preg_replace('#^storage/#', '', $user->photo);
                if (Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }
            $path = $request->file('photo')->store('users', 'public');
            $data['photo'] = 'storage/' . $path;
        }

        $user->update($data);
        return response()->json(['data' => new UserResource($user->fresh())]);
    }

    /**
     * Soft delete user
     */
    public function destroy(Request $request, string $id)
    {
        $user = $this->repo->find($id);

        if ($request->user() && $request->user()->id === $user->id) {
            return response()->json(['error' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }
        if ($user->isAdmin() && !$user->isDisabled() && $this->activeAdminCount() <= 1) {
            return response()->json(['error' => 'Impossible de supprimer le dernier administrateur actif.'], 422);
        }

        $user->tokens()->delete();
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur supprimé'
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Un admin ne peut pas se désactiver, et le dernier admin actif ne peut pas perdre son rôle.
     */
    private function guardAdminChange(?User $actor, User $target, array $data): ?string
    {
        $disabling = isset($data['status']) && in_array($data['status'], User::DISABLED_STATUSES, true);
        $demoting  = isset($data['role']) && $data['role'] !== 'admin';

        if ($actor && $actor->id === $target->id && $disabling) {
            return 'Vous ne pouvez pas désactiver votre propre compte.';
        }

        if ($target->isAdmin() && !$target->isDisabled() && ($disabling || $demoting) && $this->activeAdminCount() <= 1) {
            return 'Impossible : ce compte est le dernier administrateur actif.';
        }

        return null;
    }

    private function activeAdminCount(): int
    {
        return User::where('role', 'admin')->whereNotIn('status', User::DISABLED_STATUSES)->count();
    }
}
