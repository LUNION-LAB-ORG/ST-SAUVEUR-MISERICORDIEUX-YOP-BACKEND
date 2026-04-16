<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ParticipantEvent
 * 
 * @property int $id
 * @property string $fullname
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $message
 * @property int $event_id
 * @property string $payment_status
 * @property string|null $wave_checkout_id
 * @property string|null $payment_reference
 * @property float|null $amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 * 
 * @property Event $event
 *
 * @package App\Models
 */
class ParticipantEvent extends Model
{
	use SoftDeletes;

	protected $casts = [
		'event_id' => 'int'
	];

	protected $guarded = [];

	public function event()
	{
		return $this->belongsTo(Event::class);
	}
}
