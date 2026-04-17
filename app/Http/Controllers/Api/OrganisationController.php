<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrganisationResource;
use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganisationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organisation::query();

        if ($request->filled('status')) {
            $query->where('request_status', $request->status);
        }
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%' . $request->email . '%');
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->to);
        }

        $perPage = (int) $request->input('per_page', 15);
        $orgs    = $query->orderBy(
            $request->input('sort_by', 'id'),
            $request->input('sort_dir', 'desc')
        )->paginate($perPage);

        return OrganisationResource::collection($orgs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                 => 'nullable|string|max:255',
            'location_at'           => 'nullable|string|max:255',
            'image'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'isParishMember'        => 'required|in:yes,no',
            'movement'              => 'nullable|string|max:255',
            'email'                 => 'required|email|max:255',
            'eventType'             => 'required|string|max:255',
            'date'                  => 'required|date',
            'startTime'             => 'required|string|max:10',
            'endTime'               => 'required|string|max:10',
            'description'           => 'required|string|min:10',
            'estimatedParticipants' => 'nullable|string|max:20',
            'request_status'        => 'sometimes|in:pending,accepted,canceled',
            'is_paid'               => 'sometimes|boolean',
            'price'                 => 'sometimes|nullable|numeric|min:0',
            'pricing_tiers'         => 'sometimes|nullable|array',
            'pricing_tiers.*.label' => 'required_with:pricing_tiers|string|max:100',
            'pricing_tiers.*.amount' => 'required_with:pricing_tiers|numeric|min:0',
            'pricing_tiers.*.description' => 'nullable|string|max:255',
            'pricing_tiers.*.max_participants' => 'nullable|integer|min:1',
            'max_participants'      => 'sometimes|nullable|integer|min:1',
            'registration_deadline' => 'sometimes|nullable|date',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = 'storage/' . $request->file('image')->store('organisations', 'public');
        }

        $org = Organisation::create([
            'title'                   => $data['title'] ?? null,
            'location_at'             => $data['location_at'] ?? null,
            'image'                   => $imagePath,
            'is_parish_member'        => $data['isParishMember'],
            'movement'                => $data['movement'] ?? null,
            'email'                   => $data['email'],
            'event_type'              => $data['eventType'],
            'date'                    => $data['date'],
            'start_time'              => $data['startTime'],
            'end_time'                => $data['endTime'],
            'description'             => $data['description'],
            'estimated_participants'  => $data['estimatedParticipants'] ?? null,
            'request_status'          => $data['request_status'] ?? 'pending',
            'is_paid'                 => $data['is_paid'] ?? false,
            'price'                   => $data['price'] ?? null,
            'pricing_tiers'           => $data['pricing_tiers'] ?? null,
            'max_participants'        => $data['max_participants'] ?? null,
            'registration_deadline'   => $data['registration_deadline'] ?? null,
        ]);

        return (new OrganisationResource($org))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        return new OrganisationResource(Organisation::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $org = Organisation::findOrFail($id);

        $data = $request->validate([
            'title'                 => 'sometimes|nullable|string|max:255',
            'location_at'           => 'sometimes|nullable|string|max:255',
            'image'                 => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'isParishMember'        => 'sometimes|in:yes,no',
            'movement'              => 'sometimes|nullable|string|max:255',
            'email'                 => 'sometimes|email|max:255',
            'eventType'             => 'sometimes|string|max:255',
            'date'                  => 'sometimes|date',
            'startTime'             => 'sometimes|string|max:10',
            'endTime'               => 'sometimes|string|max:10',
            'description'           => 'sometimes|string|min:10',
            'estimatedParticipants' => 'sometimes|nullable|string|max:20',
            'request_status'        => 'sometimes|in:pending,accepted,canceled',
            'is_paid'               => 'sometimes|boolean',
            'price'                 => 'sometimes|nullable|numeric|min:0',
            'pricing_tiers'         => 'sometimes|nullable|array',
            'pricing_tiers.*.label' => 'required_with:pricing_tiers|string|max:100',
            'pricing_tiers.*.amount' => 'required_with:pricing_tiers|numeric|min:0',
            'pricing_tiers.*.description' => 'nullable|string|max:255',
            'pricing_tiers.*.max_participants' => 'nullable|integer|min:1',
            'max_participants'      => 'sometimes|nullable|integer|min:1',
            'registration_deadline' => 'sometimes|nullable|date',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = 'storage/' . $request->file('image')->store('organisations', 'public');
        }

        $mapping = [
            'isParishMember'        => 'is_parish_member',
            'eventType'             => 'event_type',
            'startTime'             => 'start_time',
            'endTime'               => 'end_time',
            'estimatedParticipants' => 'estimated_participants',
        ];

        $updates = [];
        foreach ($data as $key => $value) {
            $dbKey          = $mapping[$key] ?? $key;
            $updates[$dbKey] = $value;
        }

        $org->update($updates);

        return new OrganisationResource($org->fresh());
    }

    public function destroy(string $id)
    {
        Organisation::findOrFail($id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Demande supprimée',
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Convertir une demande d'organisation acceptée en événement officiel
     * POST /api/organisations/{id}/convert-to-event
     */
    public function convertToEvent(string $id): JsonResponse
    {
        $org = Organisation::findOrFail($id);

        if ($org->request_status !== 'accepted') {
            return response()->json([
                'error' => "La demande doit être acceptée avant d'être convertie en événement.",
            ], 422);
        }

        if ($org->converted_event_id) {
            return response()->json([
                'error' => 'Cette demande a déjà été convertie en événement (ID ' . $org->converted_event_id . ').',
            ], 422);
        }

        $title = $org->title ?: ucfirst($org->event_type) . ($org->movement ? ' — ' . ucfirst($org->movement) : '');

        $event = Event::create([
            'title'                  => $title,
            'description'            => $org->description,
            'date_at'                => $org->date,
            'time_at'                => $org->start_time,
            'location_at'            => $org->location_at ?: 'Paroisse Saint-Sauveur',
            'image'                  => $org->image,
            'is_paid'                => $org->is_paid ?? false,
            'price'                  => $org->price,
            'pricing_tiers'          => $org->pricing_tiers,
            'max_participants'       => $org->max_participants,
            'registration_deadline'  => $org->registration_deadline,
        ]);

        $org->update(['converted_event_id' => $event->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Demande convertie en événement avec succès.',
            'event'  => new EventResource($event),
            'organisation' => new OrganisationResource($org->fresh()),
        ], Response::HTTP_CREATED);
    }
}
