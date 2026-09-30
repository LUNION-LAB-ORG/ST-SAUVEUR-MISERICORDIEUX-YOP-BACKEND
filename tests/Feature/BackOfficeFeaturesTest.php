<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Homily;
use App\Models\HistoryMilestone;
use App\Models\Listen;
use App\Models\LiturgyDay;
use App\Models\MassSchedule;
use App\Models\Mess;
use App\Models\ParticipantEvent;
use App\Models\Priest;
use App\Models\Publication;
use App\Models\ScheduleException;
use App\Models\Setting;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WhatsappSubscriber;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackOfficeFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Lundi 5 octobre 2026, 08:00 (Abidjan)
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00', 'Africa/Abidjan'));
    }

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->role($role)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /** Contenu CSV : BOM UTF-8 + lignes séparées par « ; ». */
    private function csv($response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        return array_map(fn ($line) => str_getcsv($line, ';'), array_values(array_filter(explode("\n", substr($content, 3)))));
    }

    public function test_scheduled_homily_is_hidden_until_publish_at(): void
    {
        Homily::create(['date' => '2026-10-05', 'title' => 'Publiée', 'content' => 'x']);
        $scheduled = Homily::create(['date' => '2026-10-06', 'title' => 'Programmée', 'content' => 'x', 'publish_at' => '2026-10-06 06:00:00']);
        Homily::create(['date' => '2026-10-07', 'title' => 'Brouillon', 'content' => 'x', 'status' => 'draft']);
        LiturgyDay::create(['date' => '2026-10-06', 'readings' => [], 'imported_at' => now()]);

        $this->getJson('/api/homilies')->assertJsonCount(1, 'data')->assertJsonPath('data.0.state', 'published');
        $this->getJson("/api/homilies/{$scheduled->id}")->assertNotFound();
        $this->getJson('/api/liturgy?date=2026-10-06')->assertOk()->assertJsonPath('data.homily', null);

        $this->admin('priest');
        $this->getJson("/api/homilies/{$scheduled->id}")->assertOk()
            ->assertJsonPath('data.state', 'scheduled')
            ->assertJsonPath('data.publish_at', '2026-10-06 06:00:00');
        $this->getJson('/api/homilies?all=1')->assertJsonCount(3, 'data');

        $this->travelTo(Carbon::parse('2026-10-06 06:00:01', 'Africa/Abidjan'));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/liturgy?date=2026-10-06')->assertJsonPath('data.homily.title', 'Programmée');
    }

    public function test_homily_audio_upload(): void
    {
        Storage::fake('public');
        $homily = Homily::create(['date' => '2026-10-05', 'title' => 'H', 'content' => 'x']);
        $this->admin('priest');

        $response = $this->post("/api/homilies/{$homily->id}/audio", [
            'audio' => UploadedFile::fake()->create('homelie.mp3', 500, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringStartsWith(rtrim(env('APP_URL'), '/') . '/storage/homilies/audio/', $response->json('data.audio_url'));

        $this->post("/api/homilies/{$homily->id}/audio", [
            'audio' => UploadedFile::fake()->create('homelie.wav', 500, 'audio/wav'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_reorder(): void
    {
        $a = HistoryMilestone::create(['year' => '1998', 'title' => 'A']);
        $b = HistoryMilestone::create(['year' => '2005', 'title' => 'B']);
        $c = HistoryMilestone::create(['year' => '2020', 'title' => 'C']);

        $this->admin('secretariat');
        $this->postJson('/api/admin/reorder', ['resource' => 'history-milestones', 'ids' => [$c->id, $a->id, $b->id]])->assertForbidden();

        $this->admin('communication');
        $this->postJson('/api/admin/reorder', ['resource' => 'history-milestones', 'ids' => [$c->id, $a->id, $b->id]])->assertOk();
        $this->assertSame(['C', 'A', 'B'], array_column($this->getJson('/api/history-milestones')->json('data'), 'title'));

        $this->postJson('/api/admin/reorder', ['resource' => 'history-milestones', 'ids' => [$a->id, 999]])->assertUnprocessable();
        $this->postJson('/api/admin/reorder', ['resource' => 'users', 'ids' => [1]])->assertUnprocessable();
        $this->postJson('/api/admin/reorder', ['resource' => 'councils', 'ids' => [1]])->assertForbidden();
    }

    public function test_closed_registrations_and_participants_export(): void
    {
        $event = Event::create(['title' => 'Retraite', 'date_at' => '2026-10-20', 'time_at' => '09:00', 'location_at' => 'Église', 'max_participants' => 50]);
        ParticipantEvent::create(['event_id' => $event->id, 'fullname' => 'Awa Koné', 'phone' => '+2250700000001', 'attendees' => 3, 'payment_status' => 'free']);

        $this->getJson("/api/events/{$event->id}")
            ->assertJsonPath('data.registrations_open', true)
            ->assertJsonPath('data.registrations_count', 1)
            ->assertJsonPath('data.attendees_count', 3);

        $this->admin('communication');
        $this->putJson("/api/events/{$event->id}", ['registrations_open' => false])->assertOk()->assertJsonPath('data.registrations_open', false);

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/events/{$event->id}/register", ['fullname' => 'B'])
            ->assertUnprocessable()->assertJsonPath('error', 'Les inscriptions sont fermées.');

        $this->admin('secretariat');
        $this->getJson("/api/events/{$event->id}/participants")->assertOk()
            ->assertJsonPath('data.0.fullname', 'Awa Koné')
            ->assertJsonPath('data.0.attendees', 3)
            ->assertJsonStructure(['data' => [['id', 'fullname', 'phone', 'email', 'attendees', 'reminder', 'payment_status', 'tier_label', 'created_at']]]);

        $rows = $this->csv($this->get("/api/events/{$event->id}/participants/export")->assertOk());
        $this->assertSame(['Nom', 'Téléphone (WhatsApp)', 'E-mail', 'Personnes', 'Rappel', 'Paiement', 'Tarif', 'Montant (FCFA)', 'Inscrit le'], $rows[0]);
        $this->assertSame(['Awa Koné', '+2250700000001', '', '3', 'Oui', 'Gratuit'], array_slice($rows[1], 0, 6));
    }

    public function test_publication_comments_can_be_closed(): void
    {
        $publication = Publication::create(['type' => 'text', 'title' => 'P', 'allow_comments' => false, 'show_likes' => false]);

        $this->getJson("/api/publications/{$publication->id}")
            ->assertJsonPath('data.allow_comments', false)
            ->assertJsonPath('data.show_likes', false);
        $this->postJson("/api/publications/{$publication->id}/comments", ['author' => 'A', 'content' => 'x'])->assertUnprocessable();
    }

    public function test_hero_banner_settings_are_editable_and_links_validated(): void
    {
        $this->assertSame('/nouvelle-eglise', Setting::find('hero.primary_url')->value);

        $this->admin('communication');
        $carte = $this->putJson('/api/settings', ['settings' => [
            ['key' => 'hero.eyebrow', 'value' => 'Bienvenue'],
            ['key' => 'hero.title', 'value' => 'Saint Sauveur'],
            ['key' => 'hero.image_caption', 'value' => 'Maquette 2026'],
            ['key' => 'hero.link_url', 'value' => 'https://exemple.ci/projet'],
            ['key' => 'hero.secondary_url', 'value' => '/agenda'],
        ]])->assertOk()->json('data');
        $this->assertSame('Saint Sauveur', $carte['hero.title']);
        $this->assertSame('/agenda', $carte['hero.secondary_url']);

        foreach (['javascript:alert(1)', '//evil.test', 'ftp://x'] as $lien) {
            $this->putJson('/api/settings', ['settings' => [['key' => 'hero.primary_url', 'value' => $lien]]])
                ->assertUnprocessable();
        }
        $this->assertSame('/nouvelle-eglise', Setting::find('hero.primary_url')->value);
    }

    public function test_setting_change_is_logged_with_text_key(): void
    {
        $this->admin();
        $this->putJson('/api/settings', ['settings' => [['key' => 'hero.title', 'value' => 'Nouveau titre']]])->assertOk();

        // Clé de paramètre texte : jamais dans subject_id (colonne entière, rejet MySQL 1366)
        $log = \App\Models\ActivityLog::where('subject_type', 'Setting')->latest('id')->firstOrFail();
        $this->assertNull($log->subject_id);
        $this->assertSame('hero.title', $log->subject_key);
    }

    public function test_secret_setting_is_never_exposed(): void
    {
        $this->admin();
        $this->putJson('/api/settings', ['settings' => [
            ['key' => 'payment.aggregator', 'value' => 'CinetPay'],
            ['key' => 'payment.api_key', 'value' => 'sk_live_TRES_SECRET'],
        ]])->assertOk()
            ->assertJsonPath('data.payment\.api_key', null);

        $this->assertSame('sk_live_TRES_SECRET', Setting::rawValue('payment.api_key'));
        $this->assertNull(Setting::allAsMap()['payment.api_key']);

        foreach (['/api/settings', '/api/settings/map', '/api/admin/integrations'] as $url) {
            $this->assertStringNotContainsString('TRES_SECRET', $this->getJson($url)->assertOk()->getContent());
        }
        $this->assertNull($this->getJson('/api/settings/map')->json('data')['payment.api_key']);
        $item = collect($this->getJson('/api/settings')->json('data.payment'))->firstWhere('key', 'payment.api_key');
        $this->assertSame(['value' => null, 'type' => 'secret', 'is_set' => true], array_intersect_key($item, array_flip(['value', 'type', 'is_set'])));

        // Une valeur vide renvoyée par le formulaire n'efface pas le secret
        $this->putJson('/api/settings', ['settings' => [['key' => 'payment.api_key', 'value' => null]]])->assertOk();
        $this->assertSame('sk_live_TRES_SECRET', Setting::rawValue('payment.api_key'));

        $payment = collect($this->getJson('/api/admin/integrations')->json('data'))->firstWhere('key', 'payment');
        $this->assertSame('connected', $payment['status']);
    }

    public function test_json_and_boolean_settings(): void
    {
        $sections = $this->getJson('/api/settings/map')->json('data')['home.sections'];
        $this->assertIsString($sections);
        $this->assertSame(
            ['infos', 'horaires', 'parole', 'eglise', 'mouvements', 'actualites', 'histoire', 'equipe', 'whatsapp'],
            array_column(json_decode($sections, true), 'key')
        );
        $this->assertSame('1', Setting::find('whatsapp.auto_parole')->value);
        $this->assertSame('0', Setting::find('images.logo_custom')->value);

        $this->admin('communication');
        $this->putJson('/api/settings', ['settings' => [['key' => 'home.sections', 'value' => '{pas du json']]])->assertUnprocessable();
        $this->putJson('/api/settings', ['settings' => [['key' => 'home.sections', 'value' => '[{"key":"infos","visible":false}]']]])->assertOk();
        $this->assertSame('[{"key":"infos","visible":false}]', $this->getJson('/api/settings/map')->json('data')['home.sections']);
        $this->putJson('/api/settings', ['settings' => [['key' => 'whatsapp.auto_parole', 'value' => '0']]])->assertForbidden();

        Storage::fake('public');
        Setting::create(['key' => 'images.logo', 'group' => 'images', 'type' => 'image', 'label' => 'Logo', 'value' => '']);
        $this->post('/api/settings/upload-image', ['key' => 'images.logo', 'image' => UploadedFile::fake()->create('logo.png', 20, 'image/png')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('1', Setting::find('images.logo_custom')->value);
    }

    private function massSetup(): array
    {
        $slots = [];
        foreach ([1, 2, 3] as $weekday) {
            $slots[$weekday] = TimeSlot::create(['type' => 'messe', 'weekday' => $weekday, 'start_time' => '06:30:00', 'end_time' => '07:15:00', 'label' => 'Messe matinale', 'capacity' => 1, 'is_available' => true]);
        }

        return $slots;
    }

    private function massPayload(array $overrides = []): array
    {
        return $overrides + [
            'intention_type' => 'Action de grâce', 'for_whom' => 'Famille Kouadio', 'intention' => 'Merci',
            'date' => '2026-10-05', 'fullname' => 'Marie', 'phone' => '+2250700000001',
            'offering' => 'free', 'amount' => 2000, 'payment_method' => 'cash',
        ];
    }

    public function test_secretariat_entry_and_moving_a_scheduled_mass(): void
    {
        $slots = $this->massSetup();
        $this->admin('secretariat');

        // Saisie au secrétariat : sans délai minimal (lundi 06:30 est déjà passé), espèces → payée
        $data = $this->postJson('/api/admin/mass-requests', $this->massPayload(['time_slot_id' => $slots[1]->id]))
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'succeeded')
            ->assertJsonPath('data.number', 'SSM-2026-0001')
            ->json('data');
        $this->postJson('/api/admin/mass-requests', $this->massPayload(['time_slot_id' => $slots[2]->id, 'date' => '2026-10-06', 'payment_method' => 'secretariat']))
            ->assertCreated()->assertJsonPath('data.payment_status', 'to_pay');
        $this->postJson('/api/admin/mass-requests', $this->massPayload(['time_slot_id' => $slots[1]->id, 'payment_method' => 'wave']))->assertUnprocessable();

        $schedule = MassSchedule::whereHas('mess', fn ($q) => $q->where('number', $data['number']))->first();
        $schedule->update(['shifted' => true]);

        // Mardi 6 : complet (capacité 1) → refusé ; mercredi 7 : annulé → refusé ; puis déplacement accepté
        $this->putJson("/api/mass-schedules/{$schedule->id}", ['date' => '2026-10-06', 'time_slot_id' => $slots[2]->id])
            ->assertUnprocessable()->assertJsonValidationErrors('time_slot_id');
        ScheduleException::create(['date' => '2026-10-07', 'time_slot_id' => $slots[3]->id, 'is_cancelled' => true]);
        $this->putJson("/api/mass-schedules/{$schedule->id}", ['date' => '2026-10-07', 'time_slot_id' => $slots[3]->id])->assertUnprocessable();
        ScheduleException::query()->delete();

        $this->putJson("/api/mass-schedules/{$schedule->id}", ['date' => '2026-10-07', 'time_slot_id' => $slots[3]->id])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-07')
            ->assertJsonPath('data.time', '06:30')
            ->assertJsonPath('data.shifted', false);

        $this->assertStringStartsWith('2026-10-07', (string) Mess::where('number', $data['number'])->first()->getRawOriginal('date_at'));
        $this->assertSame('Messe de la demande SSM-2026-0001 déplacée au 07/10/2026 à 06:30', \App\Models\ActivityLog::latest('id')->value('description'));

        $this->admin('communication');
        $this->putJson("/api/mass-schedules/{$schedule->id}", ['date' => '2026-10-07', 'time_slot_id' => $slots[3]->id])->assertForbidden();
    }

    public function test_messes_admin_filters_and_export(): void
    {
        $slots = $this->massSetup();
        $this->admin('secretariat');
        $this->postJson('/api/admin/mass-requests', $this->massPayload(['time_slot_id' => $slots[1]->id]))->assertCreated();
        $this->postJson('/api/admin/mass-requests', $this->massPayload([
            'time_slot_id' => $slots[2]->id, 'date' => '2026-10-06', 'payment_method' => 'secretariat',
            'is_confidential' => true, 'for_whom' => 'Secret', 'fullname' => 'Paul Yao',
        ]))->assertCreated();

        $this->getJson('/api/messes?status=to_pay')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.number', 'SSM-2026-0002');
        $this->getJson('/api/messes?status=paid')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/messes?status=to_process')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/messes?q=Paul')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/messes?q=SSM-2026-0001')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/messes?date=2026-10-06')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.schedules.0.date', '2026-10-06')
            ->assertJsonPath('data.0.time_slot.label', 'Messe matinale')
            ->assertJsonStructure(['data' => [['schedules' => [['id', 'date', 'time', 'label', 'shifted']]]]]);
        // Valeur historique de request_status toujours acceptée
        $this->getJson('/api/messes?status=pending')->assertJsonPath('meta.total', 2);

        $mess = Mess::where('number', 'SSM-2026-0002')->first();
        $this->putJson("/api/messes/{$mess->id}", ['payment_status' => 'succeeded', 'request_status' => 'accepted'])->assertOk()
            ->assertJsonPath('data.payment_status', 'succeeded');

        $rows = $this->csv($this->get('/api/messes/export?status=all')->assertOk());
        $this->assertSame(['N°', 'Date(s) des messes', 'Créneau', 'Type d’intention', 'Pour qui', 'Intention', 'Formule', 'Offrande (FCFA)', 'Paiement', 'Demandeur', 'Téléphone'], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame(['SSM-2026-0002', '06/10/2026 06:30', 'Messe matinale', 'Action de grâce', 'Intention confidentielle', '', 'Messe unique', '2000'], array_slice($rows[2], 0, 8));
        $this->assertStringNotContainsString('Secret', implode(';', $rows[2]));
    }

    public function test_donations_filters_total_and_export(): void
    {
        foreach ([[10000, 'succeeded', 'wave', 'Nouvelle église'], [5000, 'succeeded', 'especes', 'Nouvelle église'], [7000, 'pending', 'wave', 'Nouvelle église'], [3000, 'succeeded', 'wave', 'Fonctionnement']] as [$amount, $status, $method, $project]) {
            Donation::create(['donator' => 'Donateur é', 'amount' => $amount, 'project' => $project, 'paymethod' => $method, 'payment_status' => $status, 'donation_at' => '2026-10-02']);
        }
        $this->admin('treasurer');

        $this->getJson('/api/donations')->assertJsonPath('meta.total', 4)->assertJsonPath('meta.total_amount', 18000);
        $this->getJson('/api/donations?project=Nouvelle%20%C3%A9glise')->assertJsonPath('meta.total_amount', 15000);
        $this->getJson('/api/donations?method=wave')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.total_amount', 13000);
        $this->getJson('/api/donations?status=pending')->assertJsonPath('meta.total', 1)->assertJsonPath('meta.total_amount', 0);
        $this->getJson('/api/donations?from=2026-10-03')->assertJsonPath('meta.total', 0);

        $rows = $this->csv($this->get('/api/donations/export?status=succeeded')->assertOk());
        $this->assertSame('Date', $rows[0][0]);
        $this->assertContains('Montant (FCFA)', $rows[0]);
        $this->assertCount(4, $rows);
        $this->assertSame(['02/10/2026', 'Donateur é'], array_slice($rows[1], 0, 2));
    }

    public function test_subscriptions_stats_and_source(): void
    {
        $this->postJson('/api/subscriptions', ['phone' => '+2250700000001', 'consent' => true, 'source' => 'home'])
            ->assertCreated()->assertJsonPath('data.source', 'home');
        $this->postJson('/api/subscriptions', ['phone' => '+2250700000002', 'consent' => true, 'lists' => ['parole']]);
        $this->postJson('/api/subscriptions', ['phone' => '+2250700000003', 'consent' => true]);
        $this->deleteJson('/api/subscriptions', ['phone' => '+2250700000003']);
        WhatsappSubscriber::create(['phone' => '+2250700000004', 'lists' => ['annonces'], 'unsubscribed_at' => now()->subDays(40)]);

        $this->admin('communication');
        $this->getJson('/api/subscriptions/stats')->assertOk()
            ->assertExactJson(['data' => ['lists' => ['parole' => 2, 'annonces' => 1], 'unsubscribed_30d' => 1]]);
    }

    public function test_listen_assignment_and_new_statuses(): void
    {
        $priest = Priest::create(['fullname' => 'Père Paul', 'function' => 'Curé']);
        $listen = Listen::create(['fullname' => 'Marie', 'type' => 'Confession', 'request_status' => 'pending']);

        // Public : le statut fourni est ignoré
        $this->postJson('/api/listens', ['fullname' => 'X', 'request_status' => 'confirmed'])->assertCreated()->assertJsonPath('data.request_status', 'pending');

        $this->admin('secretariat');
        $this->putJson("/api/listens/{$listen->id}", [
            'assigned_priest_id' => $priest->id, 'proposed_at' => '2026-10-08 17:00:00', 'request_status' => 'confirmed',
        ])->assertOk()
            ->assertJsonPath('data.assigned_priest', ['id' => $priest->id, 'fullname' => 'Père Paul', 'function' => 'Curé'])
            ->assertJsonPath('data.proposed_at', '2026-10-08 17:00:00')
            ->assertJsonPath('data.request_status', 'confirmed');
        $this->putJson("/api/listens/{$listen->id}", ['request_status' => 'closed'])->assertOk();

        $this->admin('communication');
        $this->putJson("/api/listens/{$listen->id}", ['request_status' => 'pending'])->assertForbidden();
    }
}
