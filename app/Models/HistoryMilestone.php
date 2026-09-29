<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jalon de l'histoire de la paroisse.
 */
class HistoryMilestone extends Model
{
    use SoftDeletes, HasPublicationStatus;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'int',
    ];
}
