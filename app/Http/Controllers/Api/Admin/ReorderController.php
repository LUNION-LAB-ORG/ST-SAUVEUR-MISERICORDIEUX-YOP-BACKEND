<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureRole;
use App\Models\Announcement;
use App\Models\Council;
use App\Models\HistoryMilestone;
use App\Models\Priest;
use App\Models\Service;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderController extends Controller
{
    /** Ressource → [modèle, rôles autorisés (admin implicite), libellé]. */
    private const RESOURCES = [
        'services'           => [Service::class, [], 'mouvements'],
        'priests'            => [Priest::class, [], 'équipe pastorale'],
        'history-milestones' => [HistoryMilestone::class, ['communication', 'priest'], 'jalons de l’histoire'],
        'announcements'      => [Announcement::class, ['secretariat'], 'annonces'],
        'councils'           => [Council::class, [], 'conseils et services'],
    ];

    /**
     * Ordre d'affichage (glisser-déposer). POST /admin/reorder { resource, ids: [3,1,2] } → sort_order = index.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource' => 'required|string|in:' . implode(',', array_keys(self::RESOURCES)),
            'ids'      => 'required|array|min:1',
            'ids.*'    => 'integer|distinct',
        ]);

        [$model, $roles, $label] = self::RESOURCES[$data['resource']];

        if (!$request->user()->hasRole(...$roles)) {
            return EnsureRole::forbidden();
        }

        $ids = array_map('intval', $data['ids']);
        $known = $model::whereIn('id', $ids)->pluck('id')->all();
        if (count($known) !== count($ids)) {
            return response()->json([
                'message' => 'Certains éléments sont introuvables.',
                'errors'  => ['ids' => ['Certains éléments sont introuvables.']],
            ], 422);
        }

        DB::transaction(function () use ($model, $ids) {
            foreach ($ids as $index => $id) {
                // Mise à jour directe : un seul événement de journal pour tout le réordonnancement
                $model::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        ActivityLogger::log('reordered', null, 'Ordre d’affichage modifié : ' . $label);

        return response()->json(['data' => [
            'resource' => $data['resource'],
            'ids'      => $ids,
        ]]);
    }
}
