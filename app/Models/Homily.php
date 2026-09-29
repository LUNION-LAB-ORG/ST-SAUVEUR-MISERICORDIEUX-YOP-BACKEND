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
        'priest_id' => 'int',
    ];

    public function priest()
    {
        // L'auteur reste affiché même si le prêtre a été archivé
        return $this->belongsTo(Priest::class)->withTrashed();
    }
}
