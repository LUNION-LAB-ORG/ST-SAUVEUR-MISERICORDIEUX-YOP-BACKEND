<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Listen
 * 
 * @property int $id
 * @property string|null $type
 * @property string $fullname
 * @property string|null $phone
 * @property string $message
 * @property string|null $availability
 * @property int|null $time_slot_id
 * @property string $request_status
 * @property Carbon $listen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 * 
 * @property TimeSlot $time_slot
 *
 * @package App\Models
 */
class Listen extends Model
{
	use SoftDeletes;

	protected $casts = [
		'time_slot_id' => 'int',
		'listen_at' => 'datetime'
	];

	protected $guarded = [];

	public function timeSlot()
	{
		return $this->belongsTo(TimeSlot::class);
	}

	// Alias snake_case pour rétrocompatibilité
	public function time_slot()
	{
		return $this->timeSlot();
	}
}
