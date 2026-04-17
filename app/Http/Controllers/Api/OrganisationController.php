<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationResource;
use App\Models\Organisation;
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
        ]);

        $org = Organisation::create([
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
        ]);

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
}
