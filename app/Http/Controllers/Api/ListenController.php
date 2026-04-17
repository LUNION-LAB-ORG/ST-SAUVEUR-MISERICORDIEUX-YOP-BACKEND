<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listen\StoreRequest;
use App\Http\Requests\Listen\UpdateRequest;
use App\Http\Resources\ListenResource;
use App\Repositories\Contracts\ListenRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ListenController extends Controller
{
    protected ListenRepositoryInterface $repo;

    public function __construct(ListenRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List listens (paginated)
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

        if ($request->filled('time_slot_id')) {
            $conditions[] = ['time_slot_id', '=', $request->time_slot_id];
        }

        if ($request->filled('from')) {
            $conditions[] = ['listen_at', '>=', $request->from];
        }

        if ($request->filled('to')) {
            $conditions[] = ['listen_at', '<=', $request->to];
        }

        $listens = $this->repo->paginate(
            with: ['timeSlot'],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return ListenResource::collection($listens);
    }

    /**
     * Store a new listen request
     */
    public function store(StoreRequest $request)
    {
        $listen = $this->repo->create($request->validated());

        try { \App\Services\NotificationService::forListen($listen); } catch (\Throwable $e) {}

        return (new ListenResource($listen))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show listen details
     */
    public function show(string $id)
    {
        return new ListenResource($this->repo->find($id, ['timeSlot']));
    }

    /**
     * Update listen
     */
    public function update(UpdateRequest $request, string $id)
    {
        $listen = $this->repo->update($id, $request->validated());
        return new ListenResource($listen);
    }

    /**
     * Soft delete listen
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Demande supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
