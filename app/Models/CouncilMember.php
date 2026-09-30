<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Membre d'un conseil de la paroisse.
 */
class CouncilMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'int',
    ];

    public function council()
    {
        return $this->belongsTo(Council::class);
    }
}
