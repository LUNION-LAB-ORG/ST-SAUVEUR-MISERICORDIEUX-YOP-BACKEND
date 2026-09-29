<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Mess
 * 
 * @property int $id
 * @property string $type
 * @property string $fullname
 * @property string|null $email
 * @property string $phone
 * @property string|null $message
 * @property string $request_status
 * @property float $amount
 * @property Carbon $date_at
 * @property Carbon $time_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class Mess extends Model
{
	use SoftDeletes;

	protected $casts = [
		'amount' => 'float',
		'date_at' => 'datetime',
		'time_at' => 'datetime',
		'is_confidential' => 'bool',
		'masses_count' => 'int',
		'time_slot_id' => 'int',
		'will_attend' => 'bool',
		'reminder' => 'bool',
		'needs_review' => 'bool',
	];

	protected $hidden = ['access_token'];

	/** Messes programmées (demande en ligne : 1, 3 ou 9). */
	public function schedules()
	{
		return $this->hasMany(MassSchedule::class)->orderBy('date')->orderBy('time');
	}

	public function timeSlot()
	{
		return $this->belongsTo(TimeSlot::class);
	}

	protected $guarded = [];
}
