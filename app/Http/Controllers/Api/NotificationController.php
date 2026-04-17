<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    /**
     * Liste paginée, la plus récente en premier.
     * Filtres: ?is_read=0|1, ?type=messe|listen|donation|event_register|organisation
     */
    public function index(Request $request)
    {
        $query = Notification::query()->orderBy('created_at', 'desc');

        if ($request->filled('is_read')) {
            $query->where('is_read', (bool) $request->is_read);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $limit = min((int) $request->input('limit', 50), 200);
        return NotificationResource::collection($query->limit($limit)->get());
    }

    /** Nombre de notifications non lues (pour badge sidebar) */
    public function unreadCount(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'count' => Notification::where('is_read', false)->count(),
        ]);
    }

    /** Marquer une notification comme lue */
    public function markRead(string $id): \Illuminate\Http\JsonResponse
    {
        $notif = Notification::findOrFail($id);
        $notif->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(new NotificationResource($notif));
    }

    /** Marquer toutes comme lues */
    public function markAllRead(): \Illuminate\Http\JsonResponse
    {
        Notification::where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['status' => 'success']);
    }

    /** Supprimer une notification */
    public function destroy(string $id): \Illuminate\Http\Response
    {
        Notification::findOrFail($id)->delete();
        return response()->noContent();
    }
}
