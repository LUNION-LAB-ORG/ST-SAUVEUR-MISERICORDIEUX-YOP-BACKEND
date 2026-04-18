<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Http\Resources\UserResource;
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
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur supprimé'
        ], Response::HTTP_NO_CONTENT);
    }
}
