<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Council\StoreRequest;
use App\Http\Requests\Council\UpdateRequest;
use App\Http\Resources\CouncilResource;
use App\Models\Council;
use App\Services\ActivityLogger;
use App\Repositories\Contracts\CouncilRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class CouncilController extends Controller
{
    use ResolvesPublicationScope;

    protected CouncilRepositoryInterface $repo;

    public function __construct(CouncilRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste complète (non paginée) des conseils et services.
     * Public : publiés uniquement. Admin + ?all=1 : tous les statuts.
     */
    public function index(Request $request)
    {
        $items = $this->applyPublicationScope($this->repo->query(), $request)
            ->with('members')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CouncilResource::collection($items);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $members = Arr::pull($data, 'members');

        $item = DB::transaction(function () use ($data, $members) {
            $item = $this->repo->create($data);
            $this->syncMembers($item, $members);

            return $item;
        });

        return (new CouncilResource($item->fresh('members')))
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

        return new CouncilResource($item->load('members'));
    }

    public function update(UpdateRequest $request, string $id)
    {
        $item = $this->repo->find($id);
        $data = $request->validated();
        $members = Arr::pull($data, 'members');

        $item = DB::transaction(function () use ($item, $data, $members) {
            $item = $data ? $this->repo->update($item->id, $data) : $item;
            $this->syncMembers($item, $members);

            return $item;
        });

        return new CouncilResource($item->fresh('members'));
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Conseil supprimé',
        ], Response::HTTP_NO_CONTENT);
    }

    /** Remplace la liste des membres dans l'ordre reçu (null : liste inchangée). */
    private function syncMembers(Council $council, ?array $members): void
    {
        if ($members === null) {
            return;
        }

        $council->members()->delete();
        foreach (array_values($members) as $i => $m) {
            $council->members()->create([
                'name'       => trim($m['name']),
                'function'   => filled($m['function'] ?? null) ? trim($m['function']) : null,
                'phone'      => filled($m['phone'] ?? null) ? trim($m['phone']) : null,
                'sort_order' => $i,
            ]);
        }

        ActivityLogger::log('updated', $council, 'Membres du conseil « ' . $council->name . ' » mis à jour (' . count($members) . ')');
    }
}
