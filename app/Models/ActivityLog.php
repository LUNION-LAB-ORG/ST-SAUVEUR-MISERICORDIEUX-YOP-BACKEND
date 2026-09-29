<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ligne du journal d'activité du back-office.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'user_id'    => 'int',
        'subject_id' => 'int',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
