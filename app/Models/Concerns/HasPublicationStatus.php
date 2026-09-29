<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Statut de publication commun aux modules de contenu : draft | published | hidden.
 */
trait HasPublicationStatus
{
    public const STATUSES = ['draft', 'published', 'hidden'];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->getTable() . '.status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
