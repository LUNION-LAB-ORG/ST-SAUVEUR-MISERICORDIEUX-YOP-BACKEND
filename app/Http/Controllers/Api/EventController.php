<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Concerns\ResolvesPublicationScope;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\IcsCalendar;
use Carbon\Carbon;
use App\Http\Resources\EventResource;
use App\Repositories\EventRepository;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Event\StoreRequest;
use App\Http\Requests\Event\UpdateRequest;

class EventController extends Controller
{
    use ResolvesPublicationScope;

    protected EventRepository $repo;

    public function __construct(EventRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Liste paginée des événements.
     * Public : publiés uniquement (admin authentifié + ?all=1 : tous les statuts).
     * ?upcoming=1 : à venir (date ≥ aujourd'hui, tri chronologique) ; ?past=1 : passés (tri inverse).
     */
    public function index(Request $request)
    {
        $query = $this->applyPublicationScope($this->repo->query()->with('participants'), $request);

        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%' . $request->title . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('from')) {
            $query->whereDate('date_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('date_at', '<=', $request->to);
        }

        $today = Carbon::now('Africa/Abidjan')->toDateString();

        if ($request->boolean('upcoming')) {
            $query->whereDate('date_at', '>=', $today)->orderBy('date_at')->orderBy('time_at');
        } elseif ($request->boolean('past')) {
            $query->whereDate('date_at', '<', $today)->orderByDesc('date_at')->orderByDesc('time_at');
        } else {
            $query->orderBy($request->input('sort_by', 'id'), $request->input('sort_dir', 'desc'));
        }

        return EventResource::collection($query->paginate((int) $request->input('per_page', 15)));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        // Gérer l'upload d'image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            
            // Option 1: Stocker dans storage/app/public/events
            $path = $image->store('events', 'public');

            // Mettre à jour le champ image avec le chemin relatif
            $data['image'] = 'storage/' . $path;
        }

        // Créer l'événement avec le repository
        $event = $this->repo->create($data);

        return (new EventResource($event))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Détail d'un événement par id numérique ou par slug (404 si non publié, sauf admin).
     */
    public function show(string $id): EventResource
    {
        return new EventResource($this->findVisible($id));
    }

    /**
     * Fichier iCalendar de l'événement (public). GET /events/{idOrSlug}/ics
     */
    public function ics(string $id)
    {
        $event = $this->findVisible($id);

        $date  = $event->dateString();
        $start = Carbon::parse($date . ' ' . (Event::hhmm($event->getRawOriginal('time_at')) ?? '00:00'), IcsCalendar::TIMEZONE);
        $endTime = Event::hhmm($event->getRawOriginal('end_time'));
        $end = $endTime
            ? Carbon::parse($date . ' ' . $endTime, IcsCalendar::TIMEZONE)
            : $start->copy()->addHour();
        if ($end->lte($start)) {
            $end->addDay();
        }

        $frontendUrl = rtrim(config('services.wave.frontend_url'), '/');

        return (new IcsCalendar())
            ->addEvent([
                'uid'         => 'event-' . $event->id . '@saint-sauveur-misericordieux',
                'start'       => $start,
                'end'         => $end,
                'summary'     => $event->title,
                'location'    => $event->location_at,
                'description' => $event->summary,
                'url'         => $frontendUrl . '/agenda/' . $event->slug,
            ])
            ->download($event->slug . '.ics');
    }

    private function findVisible(string $idOrSlug): Event
    {
        $event = Event::findByIdOrSlug($idOrSlug);
        abort_if(!$event, 404);
        $this->ensureVisible($event);

        return $event;
    }

    public function update(UpdateRequest $request, string $id): EventResource
    {
        $data = $request->validated();

        $event = $this->repo->find($id); // récupérer l'événement existant

        // Gestion de l'image
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Supprimer l'ancienne image si elle existe
            if ($event->image && Storage::disk('public')->exists($event->image)) {
                Storage::disk('public')->delete($event->image);
            }

            // Stocker la nouvelle image
            $path = $image->store('events', 'public');
            $data['image'] = 'storage/' . $path;
        }

        // Mettre à jour l'événement
        $event = $this->repo->update($id, $data);

        return new EventResource($event);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->repo->delete($id);
        return response()->json([
            'status'  => 'success',
            'message' => 'Event supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
