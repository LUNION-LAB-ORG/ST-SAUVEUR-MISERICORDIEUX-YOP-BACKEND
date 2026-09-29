<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Concerns\StoresPublicImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Priest\StoreRequest;
use App\Http\Requests\Priest\UpdateRequest;
use App\Http\Resources\PriestResource;
use App\Repositories\Contracts\PriestRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PriestController extends Controller
{
    use ResolvesPublicationScope, StoresPublicImages;

    protected PriestRepositoryInterface $repo;

    public function __construct(PriestRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste complète (non paginée) des prêtres.
     * Public : publiés uniquement. Admin + ?all=1 : tous les statuts.
     */
    public function index(Request $request)
    {
        $items = $this->applyPublicationScope($this->repo->query(), $request)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return PriestResource::collection($items);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->storePublicImage($request->file('photo'), 'priests');
        } else {
            unset($data['photo']);
        }

        $item = $this->repo->create($data);

        return (new PriestResource($item->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Détail : 404 si non publié (sauf admin authentifié).
     */
    public function show(string $id)
    {
        $item = $this->repo->find($id);
        $this->ensureVisible($item);

        return new PriestResource($item);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $item = $this->repo->find($id);
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $this->deletePublicImage($item->photo);
            $data['photo'] = $this->storePublicImage($request->file('photo'), 'priests');
        } else {
            unset($data['photo']);
        }

        $item = $this->repo->update($item->id, $data);

        return new PriestResource($item);
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Prêtre supprimé',
        ], Response::HTTP_NO_CONTENT);
    }
}
