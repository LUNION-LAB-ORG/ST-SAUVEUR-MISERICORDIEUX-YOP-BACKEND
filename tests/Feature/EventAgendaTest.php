<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ParticipantEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'Africa/Abidjan'));
        config(['services.wave.frontend_url' => 'https://paroisse.test']);
    }

    private function event(string $title, array $attrs = []): Event
    {
        return Event::create($attrs + [
            'title' => $title, 'date_at' => '2026-10-10', 'time_at' => '09:00',
            'location_at' => 'Église Saint Sauveur',
        ]);
    }

    public function test_slug_is_generated_unique_and_show_accepts_id_or_slug(): void
    {
        $a = $this->event('Journée paroissiale');
        $b = $this->event('Journée paroissiale');
        $b->delete();
        $c = $this->event('Journée paroissiale');

        $this->assertSame('journee-paroissiale', $a->slug);
        $this->assertSame('journee-paroissiale-2', $b->slug);
        $this->assertSame('journee-paroissiale-3', $c->slug);

        $this->getJson('/api/events/journee-paroissiale')->assertOk()->assertJsonPath('data.id', $a->id)
            ->assertJsonPath('data.slug', 'journee-paroissiale')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.programme', []);
        $this->getJson("/api/events/{$a->id}")->assertOk()->assertJsonPath('data.slug', 'journee-paroissiale');
        $this->getJson('/api/events/inconnu')->assertNotFound();

        $draft = $this->event('Brouillon', ['status' => 'draft']);
        $this->getJson("/api/events/{$draft->slug}")->assertNotFound();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/events/{$draft->slug}")->assertOk();
    }

    public function test_admin_creates_event_with_agenda_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/events', [
            'title' => 'Messe d’ouverture de l’année pastorale', 'date_at' => '2026-10-11', 'time_at' => '09:00',
            'location_at' => 'Église', 'summary' => 'Chapeau', 'category' => 'Événement paroissial',
            'audience' => 'Toute la communauté', 'end_time' => '16:00',
            'programme' => [['time' => '09:00', 'label' => 'Messe'], ['time' => '12:30', 'label' => 'Repas']],
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'messe-douverture-de-lannee-pastorale')
            ->assertJsonPath('data.end_time', '16:00')
            ->assertJsonPath('data.audience', 'Toute la communauté')
            ->assertJsonPath('data.programme.1.label', 'Repas');

        $this->postJson('/api/events', [
            'title' => 'X', 'date_at' => '2026-10-11', 'time_at' => '09:00', 'location_at' => 'Église', 'status' => 'archived',
        ])->assertUnprocessable();
    }

    public function test_upcoming_and_past_filters_with_publication_scope(): void
    {
        $this->event('Passé ancien', ['date_at' => '2026-09-01']);
        $this->event('Passé récent', ['date_at' => '2026-09-20']);
        $this->event('Aujourd’hui', ['date_at' => '2026-10-01', 'time_at' => '18:00']);
        $this->event('Bientôt tôt', ['date_at' => '2026-10-05', 'time_at' => '08:00']);
        $this->event('Bientôt tard', ['date_at' => '2026-10-05', 'time_at' => '18:00']);
        $this->event('Brouillon à venir', ['date_at' => '2026-10-06', 'status' => 'draft']);

        $upcoming = array_column($this->getJson('/api/events?upcoming=1')->assertOk()->json('data'), 'title');
        $this->assertSame(['Aujourd’hui', 'Bientôt tôt', 'Bientôt tard'], $upcoming);

        $past = array_column($this->getJson('/api/events?past=1')->json('data'), 'title');
        $this->assertSame(['Passé récent', 'Passé ancien'], $past);

        // Comportement existant conservé (tri par id décroissant, pagination)
        $this->getJson('/api/events')->assertJsonPath('meta.total', 5)->assertJsonPath('data.0.title', 'Bientôt tard');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/events?all=1')->assertJsonPath('meta.total', 6);
    }

    public function test_ics_download(): void
    {
        $this->event('Journée paroissiale', [
            'date_at' => '2026-10-10', 'time_at' => '09:00', 'end_time' => '16:00',
            'summary' => 'Messe, repas, animations', 'location_at' => 'Église Saint Sauveur',
        ]);

        $response = $this->get('/api/events/journee-paroissiale/ics')->assertOk();

        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="journee-paroissiale.ics"', $response->headers->get('Content-Disposition'));

        $ics = $response->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('DTSTART;TZID=Africa/Abidjan:20261010T090000', $ics);
        $this->assertStringContainsString('DTEND;TZID=Africa/Abidjan:20261010T160000', $ics);
        $this->assertStringContainsString('SUMMARY:Journée paroissiale', $ics);
        $this->assertStringContainsString('LOCATION:Église Saint Sauveur', $ics);
        $this->assertStringContainsString('DESCRIPTION:Messe\, repas\, animations', $ics);
        $this->assertStringContainsString('URL:https://paroisse.test/agenda/journee-paroissiale', $ics);
    }

    public function test_register_counts_the_sum_of_attendees(): void
    {
        $event = $this->event('Retraite', ['max_participants' => 5]);

        $this->postJson("/api/events/{$event->id}/register", ['fullname' => 'A', 'phone' => '+2250700000001', 'attendees' => 3])
            ->assertCreated()
            ->assertJsonPath('participant.attendees', 3)
            ->assertJsonPath('participant.reminder', true);

        $this->postJson("/api/events/{$event->id}/register", ['fullname' => 'B', 'attendees' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Il ne reste que 2 place(s) pour cet événement.');

        $this->postJson("/api/events/{$event->id}/register", ['fullname' => 'C', 'attendees' => 2, 'reminder' => false])
            ->assertCreated()
            ->assertJsonPath('participant.reminder', false);

        $this->postJson("/api/events/{$event->id}/register", ['fullname' => 'D'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Cet événement est complet.');

        $this->postJson("/api/events/{$event->id}/register", ['fullname' => 'E', 'attendees' => 21])->assertUnprocessable();

        $this->assertSame(5, (int) ParticipantEvent::where('event_id', $event->id)->sum('attendees'));
        $this->assertSame(3, ParticipantEvent::where('fullname', 'A')->first()->attendees);
    }
}
