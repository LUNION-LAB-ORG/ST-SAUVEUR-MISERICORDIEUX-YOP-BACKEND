<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreRequest;
use App\Http\Requests\Service\UpdateRequest;
use App\Http\Resources\ServiceResource;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    protected ServiceRepositoryInterface $repo;

    public function __construct(ServiceRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $conditions = [];

        if ($request->filled('title')) {
            $conditions[] = ['title', 'LIKE', '%' . $request->title . '%'];
        }

        if ($request->filled('description')) {
            $conditions[] = ['description', 'LIKE', '%' . $request->description . '%'];
        }

        $services = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return ServiceResource::collection($services);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $service = $this->repo->create($data);

        return (new ServiceResource($service))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        return new ServiceResource($this->repo->find($id));
    }

    public function update(UpdateRequest $request, string $id)
    {
        $data = $request->validated();
        $existing = $this->repo->find($id);

        if ($request->hasFile('image')) {
            if ($existing && $existing->image && Storage::disk('public')->exists(preg_replace('#^storage/#', '', $existing->image))) {
                Storage::disk('public')->delete(preg_replace('#^storage/#', '', $existing->image));
            }
            $path = $request->file('image')->store('services', 'public');
            $data['image'] = 'storage/' . $path;
        }

        $service = $this->repo->update($id, $data);
        return new ServiceResource($service);
    }

    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Service supprimé'
        ], Response::HTTP_NO_CONTENT);
    }
}
