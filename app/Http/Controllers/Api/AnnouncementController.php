<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreRequest;
use App\Http\Requests\Announcement\UpdateRequest;
use App\Http\Resources\AnnouncementResource;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnnouncementController extends Controller
{
    use ResolvesPublicationScope;

    protected AnnouncementRepositoryInterface $repo;

    public function __construct(AnnouncementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste paginée des annonces.
     * Public : publiées ET dans leur fenêtre de visibilité.
     * Admin + ?all=1 : toutes (tous statuts, sans fenêtre).
     * Filtres : ?featured=1, ?category=
     */
    public function index(Request $request)
    {
        $query = $this->repo->query();

        if (!$this->wantsAllStatuses($request)) {
            $query->published()->visibleOn(self::today());
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $announcements = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 15));

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreRequest $request)
    {
        $announcement = $this->repo->create($request->validated());

        return (new AnnouncementResource($announcement->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Détail public : 404 si non publiée ou hors fenêtre de visibilité (sauf admin).
     */
    public function show(string $id)
    {
        $announcement = $this->repo->find($id);

        if (!$this->isAdmin() && (!$announcement->isPublished() || !$announcement->isVisibleOn(self::today()))) {
            abort(404);
        }

        return new AnnouncementResource($announcement);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $announcement = $this->repo->find($id);
        $announcement = $this->repo->update($announcement->id, $request->validated());

        return new AnnouncementResource($announcement);
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce supprimée',
        ], Response::HTTP_NO_CONTENT);
    }

    private static function today(): string
    {
        return Carbon::now('Africa/Abidjan')->toDateString();
    }
}
