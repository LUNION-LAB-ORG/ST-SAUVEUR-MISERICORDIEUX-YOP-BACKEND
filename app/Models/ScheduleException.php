<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Exception ponctuelle au planning hebdomadaire.
 *
 * @property string $date Y-m-d
 * @property string|null $start_time
 * @property string|null $label
 * @property string|null $location
 * @property bool $is_cancelled
 * @property int|null $time_slot_id
 */
class ScheduleException extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_cancelled' => 'bool',
        'time_slot_id' => 'int',
    ];

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }
}
