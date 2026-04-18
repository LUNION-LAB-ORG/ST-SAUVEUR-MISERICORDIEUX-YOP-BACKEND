<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Programmation\StoreRequest;
use App\Http\Requests\Programmation\UpdateRequest;
use App\Http\Resources\ProgrammationResource;
use App\Repositories\Contracts\ProgrammationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProgrammationController extends Controller
{
    protected ProgrammationRepositoryInterface $repo;

    public function __construct(ProgrammationRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $conditions = [];

        if ($request->filled('name')) {
            $conditions[] = ['name', 'LIKE', '%' . $request->name . '%'];
        }
        if ($request->filled('category')) {
            $conditions[] = ['category', '=', $request->category];
        }
        if ($request->filled('date_from')) {
            $conditions[] = ['date_at', '>=', $request->date_from];
        }
        if ($request->filled('date_to')) {
            $conditions[] = ['date_at', '<=', $request->date_to];
        }
        if ($request->filled('is_published')) {
            $conditions[] = ['is_published', '=', $request->boolean('is_published')];
        }

        // Pour le public, ne renvoyer que les publiées + à venir par défaut
        if (!$request->user()) {
            $conditions[] = ['is_published', '=', true];
            if (!$request->filled('all')) {
                $conditions[] = ['date_at', '>=', now()->toDateString()];
            }
        }

        $programmations = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 50),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'date_at'),
            direction: $request->input('sort_dir', 'asc'),
        );

        return ProgrammationResource::collection($programmations);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('programmations', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $programmation = $this->repo->create($data);

        return (new ProgrammationResource($programmation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        return new ProgrammationResource($this->repo->find($id));
    }

    public function update(UpdateRequest $request, string $id)
    {
        $data = $request->validated();
        $existing = $this->repo->find($id);

        if ($request->hasFile('image')) {
            if ($existing && $existing->image) {
                $old = preg_replace('#^storage/#', '', $existing->image);
                if (Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }
            $path = $request->file('image')->store('programmations', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $programmation = $this->repo->update($id, $data);
        return new ProgrammationResource($programmation);
    }

    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Programmation supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
