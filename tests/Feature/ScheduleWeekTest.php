<?php

namespace Tests\Feature;

use App\Models\ScheduleException;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleWeekTest extends TestCase
{
    use RefreshDatabase;

    private function slot(array $attrs): TimeSlot
    {
        return TimeSlot::create($attrs + ['is_available' => true, 'priest_id' => null]);
    }

    public function test_week_merges_recurring_slots_with_exceptions(): void
    {
        // Lundi : messe matinale récurrente
        $morning = $this->slot([
            'type' => 'messe', 'weekday' => 1, 'start_time' => '06:30:00', 'end_time' => '07:15:00',
            'label' => 'Messe matinale', 'location' => 'Église',
        ]);
        // Lundi : adoration sans libellé (libellé par défaut)
        $this->slot(['type' => 'adoration', 'weekday' => 1, 'start_time' => '18:00:00', 'end_time' => '19:00:00']);
        // Exclus : écoute, et créneau indisponible
        $this->slot(['type' => 'ecoute', 'weekday' => 1, 'start_time' => '10:00:00', 'end_time' => '11:00:00']);
        $this->slot(['type' => 'messe', 'weekday' => 1, 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'is_available' => false]);
        // Dimanche
        $this->slot(['type' => 'messe', 'weekday' => 0, 'start_time' => '09:00:00', 'end_time' => '11:00:00']);

        // Lundi 28/09/2026 : messe matinale annulée
        ScheduleException::create(['date' => '2026-09-28', 'time_slot_id' => $morning->id, 'is_cancelled' => true, 'label' => 'Annulée']);
        // Lundi 28/09/2026 : célébration ponctuelle
        ScheduleException::create(['date' => '2026-09-28', 'start_time' => '15:00:00', 'label' => 'Messe des défunts', 'location' => 'Cimetière']);
        // Hors semaine : ignorée
        ScheduleException::create(['date' => '2026-10-05', 'start_time' => '15:00:00', 'label' => 'Hors semaine']);

        $response = $this->getJson('/api/schedule/week?start=2026-09-28')->assertOk();

        $days = $response->json('data');
        $this->assertCount(7, $days);
        $this->assertSame('2026-09-28', $days[0]['date']);
        $this->assertSame(1, $days[0]['weekday']);
        $this->assertSame('2026-10-04', $days[6]['date']);
        $this->assertSame(0, $days[6]['weekday']);

        $monday = $days[0]['items'];
        $this->assertCount(3, $monday);

        $this->assertSame([
            'time' => '06:30', 'end_time' => '07:15', 'label' => 'Messe matinale',
            'location' => 'Église', 'type' => 'messe', 'cancelled' => true,
        ], $monday[0]);

        $this->assertSame('15:00', $monday[1]['time']);
        $this->assertSame('Messe des défunts', $monday[1]['label']);
        $this->assertSame('Cimetière', $monday[1]['location']);
        $this->assertFalse($monday[1]['cancelled']);

        $this->assertSame('18:00', $monday[2]['time']);
        $this->assertSame('Adoration du Saint-Sacrement', $monday[2]['label']);
        $this->assertFalse($monday[2]['cancelled']);

        // Mardi vide, dimanche : messe avec libellé par défaut
        $this->assertSame([], $days[1]['items']);
        $this->assertSame('Messe', $days[6]['items'][0]['label']);
        $this->assertSame('09:00', $days[6]['items'][0]['time']);

        // La semaine suivante n'est pas impactée par l'annulation
        $next = $this->getJson('/api/schedule/week?start=2026-10-05')->json('data.0.items');
        $this->assertFalse($next[0]['cancelled']);
        $this->assertSame('Hors semaine', $next[1]['label']);
    }

    public function test_week_defaults_to_current_monday(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 10:00:00', 'Africa/Abidjan'));

        $this->getJson('/api/schedule/week')
            ->assertOk()
            ->assertJsonPath('data.0.date', '2026-09-28')
            ->assertJsonPath('data.6.date', '2026-10-04');
    }

    public function test_schedule_exceptions_crud(): void
    {
        $this->postJson('/api/schedule-exceptions', ['date' => '2026-09-28', 'label' => 'x'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/schedule-exceptions', [
            'date' => '2026-09-28', 'start_time' => '15:00', 'label' => 'Messe des défunts',
        ])->assertCreated()
            ->assertJsonPath('data.start_time', '15:00')
            ->assertJsonPath('data.is_cancelled', false)
            ->json('data.id');

        // Label requis pour un ajout ponctuel
        $this->postJson('/api/schedule-exceptions', ['date' => '2026-09-28'])->assertUnprocessable();

        $this->getJson('/api/schedule-exceptions?from=2026-09-28&to=2026-09-28')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/schedule-exceptions?from=2026-09-29')->assertOk()->assertJsonCount(0, 'data');

        $this->putJson("/api/schedule-exceptions/{$id}", ['location' => 'Chapelle'])
            ->assertOk()->assertJsonPath('data.location', 'Chapelle');

        $this->deleteJson("/api/schedule-exceptions/{$id}")->assertNoContent();
        $this->assertSoftDeleted('schedule_exceptions', ['id' => $id]);
    }

    public function test_time_slots_accept_label_and_location(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/time-slots', [
            'type' => 'messe', 'weekday' => 3, 'start_time' => '18:30', 'end_time' => '19:15',
            'label' => 'Messe du soir', 'location' => 'Église',
        ])->assertCreated()
            ->assertJsonPath('data.label', 'Messe du soir')
            ->assertJsonPath('data.location', 'Église');
    }
}
