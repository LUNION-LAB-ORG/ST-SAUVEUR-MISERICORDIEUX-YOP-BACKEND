<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Organisation
 *
 * Demande d'organisation d'événement paroissial (catéchèse, chorale, etc.)
 */
class Organisation extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'is_paid' => 'boolean',
        'price' => 'decimal:2',
        'max_participants' => 'integer',
        'registration_deadline' => 'datetime',
        'pricing_tiers' => 'array',
    ];
}
