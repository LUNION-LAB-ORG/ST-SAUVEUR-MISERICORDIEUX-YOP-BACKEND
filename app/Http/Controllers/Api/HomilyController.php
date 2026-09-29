<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Homily\StoreRequest;
use App\Http\Requests\Homily\UpdateRequest;
use App\Http\Resources\HomilyResource;
use App\Repositories\Contracts\HomilyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomilyController extends Controller
{
    use ResolvesPublicationScope;

    protected HomilyRepositoryInterface $repo;

    public function __construct(HomilyRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste complète des homélies (les plus récentes d'abord). Filtre : ?date=YYYY-MM-DD
     * Public : publiées uniquement. Admin + ?all=1 : tous les statuts.
     */
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);

        $query = $this->applyPublicationScope($this->repo->query()->with('priest'), $request);

        if ($request->filled('date')) {
            $query->where('date', $request->input('date'));
        }

        $items = $query->orderByDesc('date')->orderByDesc('id')->get();

        return HomilyResource::collection($items);
    }

    public function store(StoreRequest $request)
    {
        $homily = $this->repo->create($request->validated());

        return (new HomilyResource($homily->fresh('priest')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        $homily = $this->repo->find($id, ['priest']);
        $this->ensureVisible($homily);

        return new HomilyResource($homily);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $homily = $this->repo->find($id);
        $homily = $this->repo->update($homily->id, $request->validated());

        return new HomilyResource($homily->load('priest'));
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Homélie supprimée',
        ], Response::HTTP_NO_CONTENT);
    }
}
