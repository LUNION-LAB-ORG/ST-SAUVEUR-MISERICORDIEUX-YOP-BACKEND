<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Homélie.
 *
 * @property int $id
 * @property string $date
 * @property int|null $priest_id
 * @property string $title
 * @property string $content
 * @property string|null $audio_url
 * @property string $status
 */
class Homily extends Model
{
    use SoftDeletes, HasPublicationStatus;

    protected $guarded = [];

    protected $casts = [
        'priest_id'       => 'int',
        'publish_at'      => 'datetime',
        'notify_whatsapp' => 'bool',
    ];

    /** Visible publiquement : publiée et date de publication atteinte (ou non programmée). */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->published()
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    public function isVisible(): bool
    {
        return $this->isPublished() && (!$this->publish_at || $this->publish_at->lte(now()));
    }

    /** État calculé : draft | scheduled | published. */
    public function state(): string
    {
        if (!$this->isPublished()) {
            return 'draft';
        }

        return $this->publish_at && $this->publish_at->gt(now()) ? 'scheduled' : 'published';
    }

    public function priest()
    {
        // L'auteur reste affiché même si le prêtre a été archivé
        return $this->belongsTo(Priest::class)->withTrashed();
    }
}
