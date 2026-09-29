<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conseil ou service de la paroisse.
 */
class Council extends Model
{
    use SoftDeletes, HasPublicationStatus;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'int',
    ];
}
