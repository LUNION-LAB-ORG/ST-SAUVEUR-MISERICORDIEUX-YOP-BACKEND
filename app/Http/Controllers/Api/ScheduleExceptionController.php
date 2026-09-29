<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleException\StoreRequest;
use App\Http\Requests\ScheduleException\UpdateRequest;
use App\Http\Resources\ScheduleExceptionResource;
use App\Repositories\Contracts\ScheduleExceptionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduleExceptionController extends Controller
{
    protected ScheduleExceptionRepositoryInterface $repo;

    public function __construct(ScheduleExceptionRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste publique des exceptions au planning. Filtres : ?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);

        $query = $this->repo->query();

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        $items = $query->orderBy('date')->orderBy('start_time')->orderBy('id')->get();

        return ScheduleExceptionResource::collection($items);
    }

    public function store(StoreRequest $request)
    {
        $exception = $this->repo->create($this->normalize($request->validated()));

        return (new ScheduleExceptionResource($exception->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        return new ScheduleExceptionResource($this->repo->find($id));
    }

    public function update(UpdateRequest $request, string $id)
    {
        $exception = $this->repo->find($id);
        $exception = $this->repo->update($exception->id, $this->normalize($request->validated()));

        return new ScheduleExceptionResource($exception);
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Exception supprimée',
        ], Response::HTTP_NO_CONTENT);
    }

    /** Heure stockée au format HH:MM:SS (cohérent avec time_slots). */
    private function normalize(array $data): array
    {
        if (!empty($data['start_time'])) {
            $data['start_time'] = substr($data['start_time'], 0, 5) . ':00';
        }

        return $data;
    }
}
