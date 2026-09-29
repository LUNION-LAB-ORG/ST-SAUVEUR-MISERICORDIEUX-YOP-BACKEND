<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Membre de l'équipe presbytérale.
 *
 * @property int $id
 * @property string $fullname
 * @property string $function
 * @property string|null $missions
 * @property string|null $biography
 * @property int|null $ordination_year
 * @property string|null $photo
 * @property string $status
 * @property int $sort_order
 */
class Priest extends Model
{
    use SoftDeletes, HasPublicationStatus;

    protected $guarded = [];

    protected $casts = [
        'ordination_year' => 'int',
        'sort_order'      => 'int',
    ];

    public function homilies()
    {
        return $this->hasMany(Homily::class);
    }
}
