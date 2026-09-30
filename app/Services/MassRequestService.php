<?php

namespace App\Services;

use App\Models\MassSchedule;
use App\Models\Mess;
use App\Models\ScheduleException;
use App\Models\Setting;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Demande de messe en ligne : disponibilités des créneaux, programmation
 * (messe unique, triduum, neuvaine), offrande et numérotation SSM-AAAA-NNNN.
 */
class MassRequestService
{
    public const TIMEZONE = 'Africa/Abidjan';

    public const FORMULAS = ['single' => 1, 'triduum' => 3, 'novena' => 9];

    /** Décalage maximal (jours) pour trouver une messe disponible à la même heure. */
    public const MAX_SHIFT_DAYS = 30;

    /** Seuil « presque complet » : reste ≤ 20 % de la capacité. */
    public const ALMOST_FULL_RATIO = 0.2;

    /** Paramètres (group « mass »). offering_amount = null si non renseigné. */
    public function settings(): array
    {
        $offering = trim((string) Setting::get('mass.offering_amount', ''));

        return [
            'offering_amount' => ctype_digit($offering) && (int) $offering > 0 ? (int) $offering : null,
            'min_offering'    => max(0, (int) Setting::get('mass.min_offering', '0')),
            'min_delay_hours' => max(0, (int) (Setting::get('mass.min_delay_hours', '24') ?? 24)),
        ];
    }

    /**
     * Messes célébrées un jour donné : créneaux « messe » disponibles du jour de la semaine,
     * moins ceux annulés ce jour-là par une exception, triés par heure.
     *
     * @return Collection<int, TimeSlot>
     */
    public function celebratedSlots(Carbon $date): Collection
    {
        $cancelled = ScheduleException::query()
            ->where('date', $date->toDateString())
            ->where('is_cancelled', true)
            ->whereNotNull('time_slot_id')
            ->pluck('time_slot_id')
            ->all();

        return TimeSlot::query()
            ->where('type', 'messe')
            ->where('is_available', true)
            ->where('weekday', $date->dayOfWeek)
            ->whereNotIn('id', $cancelled)
            ->get()
            ->sortBy(fn (TimeSlot $slot) => self::slotTime($slot))
            ->values();
    }

    /** Intentions déjà programmées sur (date, créneau). */
    public function taken(string $date, int $timeSlotId): int
    {
        return MassSchedule::query()
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->occupying()
            ->count();
    }

    /**
     * État d'un créneau à une date : available | almost_full | full | too_late.
     */
    public function slotInfo(Carbon $date, TimeSlot $slot, ?array $settings = null): array
    {
        $settings ??= $this->settings();
        $time = self::slotTime($slot);
        $taken = $this->taken($date->toDateString(), $slot->id);
        $capacity = $slot->capacity !== null ? (int) $slot->capacity : null;

        $start = Carbon::parse($date->toDateString() . ' ' . $time, self::TIMEZONE);

        if ($start->lt(Carbon::now(self::TIMEZONE)->addHours($settings['min_delay_hours']))) {
            $status = 'too_late';
        } elseif ($capacity !== null && $taken >= $capacity) {
            $status = 'full';
        } elseif ($capacity !== null && ($capacity - $taken) <= $capacity * self::ALMOST_FULL_RATIO) {
            $status = 'almost_full';
        } else {
            $status = 'available';
        }

        return [
            'time_slot_id' => $slot->id,
            'time'         => $time,
            'label'        => $slot->label ?: 'Messe',
            'capacity'     => $capacity,
            'taken'        => $taken,
            'status'       => $status,
        ];
    }

    /** Disponibilités sur une période (days ≤ 31). */
    public function availability(Carbon $from, int $days): array
    {
        $settings = $this->settings();
        $result = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $result[] = [
                'date'    => $date->toDateString(),
                'weekday' => $date->dayOfWeek,
                'slots'   => $this->celebratedSlots($date)
                    ->map(fn (TimeSlot $slot) => $this->slotInfo($date, $slot, $settings))
                    ->values()
                    ->all(),
            ];
        }

        return [
            'offering_amount' => $settings['offering_amount'],
            'min_offering'    => $settings['min_offering'],
            'min_delay_hours' => $settings['min_delay_hours'],
            'days'            => $result,
        ];
    }

    /**
     * Programme les messes d'une demande. Triduum / neuvaine : jours consécutifs à la même heure ;
     * un jour sans messe disponible à cette heure est décalé au jour suivant (shifted), 30 jours maximum.
     *
     * @return array<int, array{date:string,time:string,time_slot_id:int,label:string,shifted:bool}>
     * @throws ValidationException
     */
    public function plan(Carbon $date, int $timeSlotId, int $count, bool $enforceDelay = true): array
    {
        $settings = $this->settings();

        $slot = $this->celebratedSlots($date)->firstWhere('id', $timeSlotId);
        if (!$slot) {
            throw ValidationException::withMessages(['time_slot_id' => 'Aucune messe n’est célébrée sur ce créneau à cette date.']);
        }

        $info = $this->slotInfo($date, $slot, $settings);
        if ($enforceDelay && $info['status'] === 'too_late') {
            throw ValidationException::withMessages([
                'date' => "La première messe doit avoir lieu au moins {$settings['min_delay_hours']} heures après votre demande.",
            ]);
        }
        if ($info['status'] === 'full') {
            throw ValidationException::withMessages(['time_slot_id' => 'Ce créneau est complet. Merci d’en choisir un autre.']);
        }

        $time = $info['time'];
        $schedules = [[
            'date' => $date->toDateString(), 'time' => $time, 'time_slot_id' => $slot->id,
            'label' => $info['label'], 'shifted' => false,
        ]];

        $cursor = $date->copy();
        for ($i = 1; $i < $count; $i++) {
            $nominal = $cursor->copy()->addDay();
            $found = null;

            for ($offset = 0; $offset <= self::MAX_SHIFT_DAYS; $offset++) {
                $day = $nominal->copy()->addDays($offset);
                $candidate = $this->celebratedSlots($day)
                    ->first(fn (TimeSlot $s) => self::slotTime($s) === $time
                        && $this->slotInfo($day, $s, $settings)['status'] !== 'full');

                if ($candidate) {
                    $found = [$day, $candidate];
                    break;
                }
            }

            if (!$found) {
                throw ValidationException::withMessages([
                    'formula' => "Impossible de programmer toutes les messes à {$time} dans les " . self::MAX_SHIFT_DAYS . ' jours suivants. Merci de choisir une autre heure.',
                ]);
            }

            [$day, $candidate] = $found;
            $schedules[] = [
                'date'         => $day->toDateString(),
                'time'         => $time,
                'time_slot_id' => $candidate->id,
                'label'        => $candidate->label ?: 'Messe',
                'shifted'      => !$day->isSameDay($nominal),
            ];
            $cursor = $day;
        }

        return $schedules;
    }

    /**
     * Enregistre la demande et ses messes programmées. Le numéro SSM-AAAA-NNNN est attribué
     * sous verrou (transaction + lockForUpdate) ; l'index unique garantit l'absence de doublon
     * et une collision éventuelle est rejouée.
     */
    public function create(array $attributes, Carbon $date, int $timeSlotId, int $count, bool $enforceDelay = true): Mess
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $date, $timeSlotId, $count, $enforceDelay) {
                    $number = $this->nextNumber((int) Carbon::now(self::TIMEZONE)->year);

                    // Programmation sous le même verrou : la capacité est vérifiée au plus juste
                    $schedules = $this->plan($date, $timeSlotId, $count, $enforceDelay);
                    $first = $schedules[0];

                    $mess = Mess::create($attributes + [
                        'number'       => $number,
                        'access_token' => Str::random(40),
                        'date_at'      => $first['date'],
                        'time_at'      => $first['time'] . ':00',
                        'time_slot_id' => $first['time_slot_id'],
                        'masses_count' => $count,
                        'needs_review' => collect($schedules)->contains('shifted', true),
                    ]);

                    foreach ($schedules as $schedule) {
                        $mess->schedules()->create([
                            'date'         => $schedule['date'],
                            'time'         => $schedule['time'] . ':00',
                            'time_slot_id' => $schedule['time_slot_id'],
                            'label'        => $schedule['label'],
                            'shifted'      => $schedule['shifted'],
                        ]);
                    }

                    return $mess;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Déplace une messe programmée (admin) : le créneau doit être célébré ce jour-là et non complet.
     *
     * @throws ValidationException
     */
    public function move(MassSchedule $schedule, Carbon $date, int $timeSlotId): MassSchedule
    {
        return DB::transaction(function () use ($schedule, $date, $timeSlotId) {
            $slot = $this->celebratedSlots($date)->firstWhere('id', $timeSlotId);
            if (!$slot) {
                throw ValidationException::withMessages(['time_slot_id' => 'Aucune messe n’est célébrée sur ce créneau à cette date.']);
            }

            $taken = MassSchedule::query()
                ->where('date', $date->toDateString())
                ->where('time_slot_id', $slot->id)
                ->where('id', '!=', $schedule->id)
                ->occupying()
                ->count();
            if ($slot->capacity !== null && $taken >= (int) $slot->capacity) {
                throw ValidationException::withMessages(['time_slot_id' => 'Ce créneau est complet.']);
            }

            $schedule->update([
                'date'         => $date->toDateString(),
                'time'         => self::slotTime($slot) . ':00',
                'time_slot_id' => $slot->id,
                'label'        => $slot->label ?: 'Messe',
                'shifted'      => false,
            ]);

            // La date de la demande reste celle de sa première messe
            $mess = $schedule->mess;
            $first = $mess->schedules()->reorder()->orderBy('date')->orderBy('time')->first();
            if ($first) {
                $mess->forceFill([
                    'date_at'      => $first->date,
                    'time_at'      => $first->time,
                    'time_slot_id' => $first->time_slot_id,
                ])->saveQuietly();
            }

            return $schedule->fresh();
        });
    }

    /** Prochain numéro de l'année, verrouillé jusqu'à la fin de la transaction. */
    public function nextNumber(int $year): string
    {
        $prefix = 'SSM-' . $year . '-';

        $last = Mess::withTrashed()
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(number) DESC')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Heure HH:MM d'un créneau (colonne time ou datetime selon le moteur). */
    public static function slotTime(TimeSlot $slot, string $column = 'start_time'): string
    {
        $raw = (string) $slot->getRawOriginal($column);

        return preg_match('/(\d{2}):(\d{2})/', $raw, $m) ? $m[1] . ':' . $m[2] : '00:00';
    }
}
