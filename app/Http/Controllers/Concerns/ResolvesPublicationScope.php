<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Règles de visibilité communes aux modules de contenu :
 *  - public : uniquement status = published ;
 *  - admin authentifié (Sanctum) + ?all=1 : tous les statuts.
 */
trait ResolvesPublicationScope
{
    protected function isAdmin(): bool
    {
        return auth('sanctum')->check();
    }

    protected function wantsAllStatuses(Request $request): bool
    {
        return $this->isAdmin() && $request->boolean('all');
    }

    /** Restreint aux contenus publiés sauf demande admin explicite (?all=1). */
    protected function applyPublicationScope(Builder $query, Request $request): Builder
    {
        return $this->wantsAllStatuses($request) ? $query : $query->published();
    }

    /** show public : 404 si non publié (sauf admin authentifié). */
    protected function ensureVisible(Model $model): void
    {
        if (!$this->isAdmin() && $model->status !== 'published') {
            abort(404);
        }
    }
}
