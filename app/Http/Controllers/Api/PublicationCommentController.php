<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Concerns\TogglesDeviceLikes;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicationComment\StoreRequest;
use App\Http\Requests\PublicationComment\UpdateRequest;
use App\Http\Resources\PublicationCommentResource;
use App\Models\CommentLike;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Repositories\Contracts\PublicationCommentRepositoryInterface;
use App\Support\ContentMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicationCommentController extends Controller
{
    use ResolvesPublicationScope, TogglesDeviceLikes;

    protected PublicationCommentRepositoryInterface $repo;

    public function __construct(PublicationCommentRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Commentaires publiés d'une publication, du plus récent au plus ancien (public).
     */
    public function publicIndex(string $publicationId)
    {
        $publication = $this->visiblePublication($publicationId);

        $comments = $publication->comments()
            ->published()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return PublicationCommentResource::collection($comments);
    }

    /**
     * Nouveau commentaire (public) : en attente de modération ; liens et téléphones masqués.
     */
    public function store(StoreRequest $request, string $publicationId)
    {
        $publication = $this->visiblePublication($publicationId);
        $data = $request->validated();

        $comment = $this->repo->create([
            'publication_id' => $publication->id,
            'author'         => mb_substr(ContentMasker::mask(trim($data['author'])), 0, 80),
            'content'        => ContentMasker::mask(trim($data['content'])),
            'status'         => 'pending',
        ]);

        return (new PublicationCommentResource($comment->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** « J'aime » à bascule sur un commentaire publié. Body : { device_id } */
    public function like(Request $request, string $id): JsonResponse
    {
        $request->validate(['device_id' => 'required|string|max:100']);

        $comment = $this->repo->query()->published()->findOrFail($id);
        $this->visiblePublication((string) $comment->publication_id);

        return response()->json([
            'data' => $this->toggleLike(CommentLike::class, 'comment_id', $comment, $request->input('device_id')),
        ]);
    }

    /**
     * Modération (admin) : liste paginée. Filtres : ?status=pending|published|rejected, ?publication_id=
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'         => 'nullable|in:pending,published,rejected',
            'publication_id' => 'nullable|integer',
        ]);

        $query = $this->repo->query()->with('publication');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('publication_id')) {
            $query->where('publication_id', $request->input('publication_id'));
        }

        $comments = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(max(1, min(100, (int) $request->input('per_page', 15))));

        return PublicationCommentResource::collection($comments);
    }

    /** Modération (admin) : statut et/ou réponse de la paroisse. */
    public function update(UpdateRequest $request, string $id)
    {
        $comment = $this->repo->find($id);
        $data = $request->validated();

        if (array_key_exists('reply', $data)) {
            $data['reply'] = filled($data['reply']) ? trim($data['reply']) : null;
            $data['replied_at'] = $data['reply'] ? now() : null;
        }

        $comment = $this->repo->update($comment->id, $data);

        return new PublicationCommentResource($comment->load('publication'));
    }

    public function destroy(string $id)
    {
        $this->repo->find($id);
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Commentaire supprimé',
        ], Response::HTTP_NO_CONTENT);
    }

    private function visiblePublication(string $idOrSlug): Publication
    {
        $publication = Publication::findByIdOrSlug($idOrSlug);
        abort_if(!$publication || (!$this->isAdmin() && !$publication->isVisible()), 404);

        return $publication;
    }
}
