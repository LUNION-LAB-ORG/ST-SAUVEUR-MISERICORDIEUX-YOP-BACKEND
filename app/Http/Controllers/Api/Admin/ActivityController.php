<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Dernières actions du back-office. GET /admin/activities?limit=20 (100 max)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['limit' => 'nullable|integer|min:1|max:100']);

        $logs = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) $request->input('limit', 20))
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'user'        => $log->user ? ['id' => $log->user->id, 'name' => $log->user->displayName()] : null,
                'created_at'  => optional($log->created_at)->toDateTimeString(),
            ]);

        return response()->json(['data' => $logs]);
    }
}
