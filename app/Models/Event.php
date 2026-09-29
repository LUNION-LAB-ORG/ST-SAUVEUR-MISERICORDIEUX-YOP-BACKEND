<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasUniqueSlug;
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
	use SoftDeletes, HasPublicationStatus, HasUniqueSlug;

	protected $casts = [
		'date_at'               => 'datetime',
		'time_at'               => 'datetime',
		'is_paid'               => 'boolean',
		'price'                 => 'decimal:2',
		'pricing_tiers'         => 'array',
		'max_participants'      => 'integer',
		'registration_deadline' => 'datetime',
		'programme'             => 'array',
	];

	protected $attributes = [
		'programme' => '[]',
		'status'    => 'published',
	];

	protected $guarded = [];

	/** Date de l'événement (Y-m-d), quel que soit le format stocké. */
	public function dateString(): ?string
	{
		$raw = $this->getRawOriginal('date_at') ?? $this->attributes['date_at'] ?? null;
		return $raw ? substr((string) $raw, 0, 10) : null;
	}

	/** Extrait HH:MM d'une colonne heure (time ou datetime selon le moteur). */
	public static function hhmm($value): ?string
	{
		if ($value instanceof \DateTimeInterface) {
			return $value->format('H:i');
		}
		if ($value && preg_match('/(\d{2}):(\d{2})/', (string) $value, $m)) {
			return $m[1] . ':' . $m[2];
		}
		return null;
	}

	public function participants()
	{
		return $this->hasMany(ParticipantEvent::class);
	}
}
