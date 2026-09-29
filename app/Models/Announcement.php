<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Annonce paroissiale.
 *
 * @property string|null $visible_from  Y-m-d
 * @property string|null $visible_until Y-m-d
 */
class Announcement extends Model
{
    use SoftDeletes, HasPublicationStatus;

    protected $guarded = [];

    // visible_from / visible_until restent des chaînes Y-m-d (comparaisons SQL directes)
    protected $casts = [
        'is_featured' => 'bool',
        'sort_order'  => 'int',
    ];

    /** Annonces dont la fenêtre de visibilité inclut la date donnée (Y-m-d). */
    public function scopeVisibleOn(Builder $query, string $date): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('visible_from')->orWhere('visible_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('visible_until')->orWhere('visible_until', '>=', $date));
    }

    public function isVisibleOn(string $date): bool
    {
        return (!$this->visible_from || $this->visible_from <= $date)
            && (!$this->visible_until || $this->visible_until >= $date);
    }
}
