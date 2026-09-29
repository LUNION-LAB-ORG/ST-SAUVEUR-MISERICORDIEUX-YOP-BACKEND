<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\ChurchProject;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Homily;
use App\Models\Listen;
use App\Models\LiturgyDay;
use App\Models\MassSchedule;
use App\Models\Mess;
use App\Models\ParticipantEvent;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackOfficeActivityAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Jeudi 1er octobre 2026, 10:00 (Abidjan)
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'Africa/Abidjan'));
    }

    public function test_activity_log_records_readable_french_sentences(): void
    {
        $user = User::factory()->role('secretariat')->create(['fullname' => 'Marie Secrétariat']);
        Sanctum::actingAs($user);

        $this->postJson('/api/announcements', ['title' => 'Inscriptions au catéchisme', 'content' => 'x'])->assertCreated();

        $log = ActivityLog::latest('id')->first();
        $this->assertSame('Annonce publiée : Inscriptions au catéchisme', $log->description);
        $this->assertSame('created', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Announcement', $log->subject_type);

        $id = Announcement::first()->id;
        $this->putJson("/api/announcements/{$id}", ['status' => 'hidden'])->assertOk();
        $this->assertSame('Annonce masquée : Inscriptions au catéchisme', ActivityLog::latest('id')->value('description'));

        // Demande de messe marquée payée
        $mess = Mess::create(['type' => 'x', 'fullname' => 'Jean', 'phone' => '0700000000', 'date_at' => '2026-10-05', 'time_at' => '06:30:00', 'number' => 'SSM-2026-0012', 'payment_status' => 'to_pay']);
        $this->assertSame('Demande de messe SSM-2026-0012 reçue', ActivityLog::latest('id')->value('description'));
        $this->putJson("/api/messes/{$mess->id}", ['payment_status' => 'succeeded'])->assertOk();
        $this->assertSame('Demande de messe SSM-2026-0012 marquée payée', ActivityLog::latest('id')->value('description'));

        // Commentaire validé (rôle communication)
        Sanctum::actingAs(User::factory()->role('communication')->create());
        $publication = Publication::create(['type' => 'text', 'title' => 'La kermesse']);
        $comment = $publication->comments()->create(['author' => 'A', 'content' => 'x']);
        $this->putJson("/api/comments/{$comment->id}", ['status' => 'published'])->assertOk();
        $this->assertSame('Commentaire validé sur « La kermesse »', ActivityLog::latest('id')->value('description'));

        // Aucune donnée personnelle dans les phrases
        $this->assertFalse(ActivityLog::where('description', 'like', '%0700000000%')->exists());
        $this->assertFalse(ActivityLog::where('description', 'like', '%Jean%')->exists());

        $this->getJson('/api/admin/activities?limit=2')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.description', 'Commentaire validé sur « La kermesse »')
            ->assertJsonStructure(['data' => [['id', 'description', 'user' => ['id', 'name'], 'created_at']]]);
    }

    public function test_liturgy_import_is_logged(): void
    {
        Http::fake(['*' => Http::response(['informations' => ['jour_liturgique_nom' => 'Férie'], 'messes' => [['nom' => 'Messe du jour', 'lectures' => []]]], 200)]);

        $this->artisan('liturgy:import', ['--date' => '2026-10-01', '--days' => 7])->assertExitCode(0);

        $this->assertSame('Textes AELF importés : 7 jours', ActivityLog::latest('id')->value('description'));
    }

    public function test_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->role('priest')->create());

        // Messes : 2 à traiter dont 1 à régler ; célébration du jour avec 2 intentions dont 1 confidentielle
        $slot = TimeSlot::create(['type' => 'messe', 'weekday' => 4, 'start_time' => '06:30:00', 'end_time' => '07:15:00', 'label' => 'Messe matinale', 'is_available' => true, 'capacity' => 10]);
        foreach ([[false, 'to_pay'], [true, 'succeeded']] as $i => [$conf, $pay]) {
            $m = Mess::create(['type' => 'x', 'fullname' => 'x', 'phone' => 'x', 'date_at' => '2026-10-01', 'time_at' => '06:30:00', 'number' => 'SSM-2026-000' . ($i + 1), 'is_confidential' => $conf, 'payment_status' => $pay, 'request_status' => 'pending']);
            MassSchedule::create(['mess_id' => $m->id, 'date' => '2026-10-01', 'time' => '06:30:00', 'time_slot_id' => $slot->id]);
        }

        // Dons de la semaine (lundi 28/09 → maintenant) : 2 réussis, 1 en attente, 1 de la semaine précédente
        foreach ([[10000, 'succeeded', '2026-09-28 09:00:00'], [25000, 'succeeded', '2026-10-01 08:00:00'], [5000, 'pending', '2026-09-30'], [99000, 'succeeded', '2026-09-25']] as [$amount, $status, $at]) {
            Donation::create(['donator' => 'x', 'amount' => $amount, 'project' => 'Nouvelle église', 'paymethod' => 'wave', 'payment_status' => $status, 'donation_at' => $at]);
        }

        // Commentaires : 3 en attente sur 2 publications
        $p1 = Publication::create(['type' => 'text', 'title' => 'P1']);
        $p2 = Publication::create(['type' => 'text', 'title' => 'P2']);
        $p1->comments()->createMany([['author' => 'a', 'content' => 'x'], ['author' => 'b', 'content' => 'y']]);
        $p2->comments()->create(['author' => 'c', 'content' => 'z']);
        $p2->comments()->create(['author' => 'd', 'content' => 'ok', 'status' => 'published']);

        Listen::create(['fullname' => 'x', 'request_status' => 'pending']);

        // Prochain événement : 2 inscriptions (3 personnes), une inscription échouée ignorée
        $event = Event::create(['title' => 'Journée paroissiale', 'date_at' => '2026-10-11', 'time_at' => '09:00', 'location_at' => 'Église', 'max_participants' => 300]);
        Event::create(['title' => 'Passé', 'date_at' => '2026-09-01', 'time_at' => '09:00', 'location_at' => 'Église']);
        ParticipantEvent::create(['event_id' => $event->id, 'fullname' => 'a', 'attendees' => 2, 'payment_status' => 'free']);
        ParticipantEvent::create(['event_id' => $event->id, 'fullname' => 'b', 'attendees' => 1, 'payment_status' => 'free']);
        ParticipantEvent::create(['event_id' => $event->id, 'fullname' => 'c', 'attendees' => 5, 'payment_status' => 'failed']);

        // Liturgie : 3 jours importés ; homélie du jour publiée, demain programmée
        foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-09-20'] as $d) {
            LiturgyDay::create(['date' => $d, 'readings' => [], 'imported_at' => now()]);
        }
        Homily::create(['date' => '2026-10-01', 'title' => 'H', 'content' => 'x']);
        Homily::create(['date' => '2026-10-02', 'title' => 'H2', 'content' => 'x', 'publish_at' => '2026-10-02 06:00:00']);

        // Annonce qui expire dans 2 jours
        Announcement::create(['title' => 'Bientôt expirée', 'content' => 'x', 'visible_until' => '2026-10-03']);

        ChurchProject::current()->update([
            'goal_amount' => 200000, 'adjustment_amount' => 0,
            'phases' => [['name' => 'Fondations', 'status' => 'done'], ['name' => 'Gros œuvre', 'status' => 'in_progress']],
        ]);

        $data = $this->getJson('/api/admin/dashboard')->assertOk()->json('data');

        $this->assertSame(['to_process' => 2, 'to_pay' => 1], $data['masses']);
        $this->assertSame(['total' => 35000, 'count' => 2], $data['donations_week']);
        $this->assertSame(['pending' => 3, 'publications' => 2], $data['comments']);
        $this->assertSame(['pending' => 1], $data['listens']);
        $this->assertSame([
            'id' => $event->id, 'slug' => 'journee-paroissiale', 'title' => 'Journée paroissiale', 'date_at' => '2026-10-11',
            'registrations' => 2, 'attendees' => 3, 'max_participants' => 300,
        ], $data['next_event']);
        $this->assertSame(3, $data['liturgy']['imported_days']);
        $this->assertSame('published', $data['liturgy']['homily_today']);
        $this->assertSame('scheduled', $data['liturgy']['homily_tomorrow']);
        $this->assertNotNull($data['liturgy']['last_import_at']);
        $this->assertSame(['progress' => 67, 'collected_amount' => 134000, 'goal_amount' => 200000, 'current_phase' => 'Gros œuvre'], $data['church_project']);
        $this->assertSame([[
            'time_slot_id' => $slot->id, 'time' => '06:30', 'label' => 'Messe matinale', 'intentions' => 2, 'confidential' => 1,
        ]], $data['today_masses']);

        $tasks = collect($data['tasks'])->keyBy('key');
        $this->assertSame('2 demandes de messe à traiter', $tasks['masses_to_process']['label']);
        $this->assertSame('/dashboard/messes', $tasks['masses_to_process']['href']);
        $this->assertSame('3 commentaires à modérer', $tasks['comments_pending']['label']);
        $this->assertSame('1 rendez-vous en attente', $tasks['listens_pending']['label']);
        $this->assertSame('1 annonce expire dans 2 jours', $tasks['announcements_expiring']['label']);
        $this->assertTrue($tasks->has('aelf_missing'));
        $this->assertFalse($tasks->has('homily_tomorrow_missing'));
    }
}
