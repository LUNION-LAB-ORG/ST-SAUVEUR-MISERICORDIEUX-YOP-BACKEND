<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreRequest;
use App\Http\Requests\Mess\UpdateRequest;
use App\Http\Resources\MessResource;
use App\Repositories\Contracts\MessRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MesseController extends Controller
{
    protected MessRepositoryInterface $repo;

    public function __construct(MessRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List messes (paginated)
     */
    public function index(Request $request)
    {
        $conditions = [];

        // Filters
        if ($request->filled('type')) {
            $conditions[] = ['type', 'LIKE', '%' . $request->type . '%'];
        }

        if ($request->filled('fullname')) {
            $conditions[] = ['fullname', 'LIKE', '%' . $request->fullname . '%'];
        }

        if ($request->filled('phone')) {
            $conditions[] = ['phone', 'LIKE', '%' . $request->phone . '%'];
        }

        if ($request->filled('status')) {
            $conditions[] = ['request_status', '=', $request->status];
        }

        if ($request->filled('from')) {
            $conditions[] = ['date_at', '>=', $request->from];
        }

        if ($request->filled('to')) {
            $conditions[] = ['date_at', '<=', $request->to];
        }

        $messes = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return MessResource::collection($messes);
    }

    /**
     * Store a new messe
     */
    public function store(StoreRequest $request)
    {
        $messe = $this->repo->create($request->validated());

        // Notification admin
        try { \App\Services\NotificationService::forMesse($messe); } catch (\Throwable $e) {}

        return (new MessResource($messe))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display messe
     */
    public function show(string $id)
    {
        return new MessResource($this->repo->find($id));
    }

    /**
     * Update messe
     */
    public function update(UpdateRequest $request, string $id)
    {
        $messe = $this->repo->update($id, $request->validated());
        return new MessResource($messe);
    }

    /**
     * Soft delete messe
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Messe supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
