<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Concerns\StoresPublicImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Homily\StoreRequest;
use App\Http\Requests\Homily\UpdateRequest;
use App\Http\Resources\HomilyResource;
use App\Repositories\Contracts\HomilyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomilyController extends Controller
{
    use ResolvesPublicationScope, StoresPublicImages;

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

        $query = $this->repo->query()->with('priest');
        if (!$this->wantsAllStatuses($request)) {
            // Publiées et date de publication atteinte (homélies programmées masquées)
            $query->visible();
        }

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
        abort_if(!$this->isAdmin() && !$homily->isVisible(), 404);

        return new HomilyResource($homily);
    }

    public function update(UpdateRequest $request, string $id)
    {
        $homily = $this->repo->find($id);
        $homily = $this->repo->update($homily->id, $request->validated());

        return new HomilyResource($homily->load('priest'));
    }

    /**
     * Enregistrement audio de l'homélie (admin). Multipart : `audio` (mp3, m4a — 20 Mo max).
     */
    public function uploadAudio(Request $request, string $id)
    {
        $request->validate([
            'audio' => [
                'required', 'file', 'max:20480',
                // mp3 est détecté « mpga », m4a « mp4 » selon le type MIME : on accepte ces alias
                'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/m4a,audio/aac,video/mp4',
                function ($attribute, $file, $fail) {
                    if (!in_array(strtolower($file->getClientOriginalExtension()), ['mp3', 'm4a'], true)) {
                        $fail('Le fichier audio doit être au format mp3 ou m4a.');
                    }
                },
            ],
        ]);

        $homily = $this->repo->find($id);
        $this->deletePublicImage($homily->audio_url);

        $path = 'storage/' . $request->file('audio')->storeAs(
            'homilies/audio',
            \Illuminate\Support\Str::random(40) . '.' . strtolower($request->file('audio')->getClientOriginalExtension()),
            'public'
        );
        $homily->update(['audio_url' => $path]);

        return new HomilyResource($homily->fresh('priest'));
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
