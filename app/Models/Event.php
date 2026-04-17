<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Event
 * 
 * @property int $id
 * @property string $title
 * @property Carbon $date_at
 * @property Carbon $time_at
 * @property string $location_at
 * @property string|null $description
 * @property string|null $image
 * @property bool $is_paid
 * @property float|null $price
 * @property int|null $max_participants
 * @property Carbon|null $registration_deadline
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|ParticipantEvent[] $participant_events
 *
 * @package App\Models
 */
class Event extends Model
{
	use SoftDeletes;

	protected $casts = [
		'date_at'               => 'datetime',
		'time_at'               => 'datetime',
		'is_paid'               => 'boolean',
		'price'                 => 'decimal:2',
		'pricing_tiers'         => 'array',
		'max_participants'      => 'integer',
		'registration_deadline' => 'datetime',
	];

	protected $guarded = [];

	public function participants()
	{
		return $this->hasMany(ParticipantEvent::class);
	}
}
