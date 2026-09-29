<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Messe programmée pour une demande d'intention (date, heure, créneau).
 *
 * @property string $date Y-m-d
 * @property string $time HH:MM[:SS]
 */
class MassSchedule extends Model
{
    protected $guarded = [];

    // `date` et `time` restent des chaînes (comparaisons SQL directes)
    protected $casts = [
        'mess_id'      => 'int',
        'time_slot_id' => 'int',
        'shifted'      => 'bool',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /**
     * Messes qui occupent une place : demande non annulée, et paiement Wave non abandonné
     * (une session Wave en attente depuis plus d'une heure est considérée comme abandonnée).
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereHas('mess', function ($q) {
            $q->where('request_status', '!=', 'canceled')
                ->where(function ($q2) {
                    $q2->where('payment_method', '!=', 'wave')
                        ->orWhereNull('payment_method')
                        ->orWhere('payment_status', '!=', 'pending')
                        ->orWhere('created_at', '>', now()->subHour());
                });
        });
    }

    public function hhmm(): string
    {
        return substr((string) $this->time, 0, 5);
    }
}
