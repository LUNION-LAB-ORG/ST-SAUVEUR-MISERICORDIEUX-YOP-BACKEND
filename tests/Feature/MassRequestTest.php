<?php

namespace Tests\Feature;

use App\Models\MassSchedule;
use App\Models\Mess;
use App\Models\ScheduleException;
use App\Models\Setting;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Demande de messe en ligne. « Maintenant » = lundi 5 octobre 2026, 08:00 (Abidjan) ; délai minimal 24 h.
 */
class MassRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, TimeSlot> créneau 06:30 par jour de la semaine */
    private array $morning = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00', 'Africa/Abidjan'));

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $this->morning[$weekday] = TimeSlot::create([
                'type' => 'messe', 'weekday' => $weekday, 'start_time' => '06:30:00', 'end_time' => '07:15:00',
                'label' => 'Messe matinale', 'location' => 'Église', 'capacity' => 5, 'is_available' => true,
            ]);
        }
        // Créneaux ignorés : autre type, indisponible
        TimeSlot::create(['type' => 'adoration', 'weekday' => 3, 'start_time' => '17:00:00', 'end_time' => '18:00:00', 'is_available' => true]);
        TimeSlot::create(['type' => 'messe', 'weekday' => 3, 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'is_available' => false]);
        // Messe du soir le mercredi
        TimeSlot::create(['type' => 'messe', 'weekday' => 3, 'start_time' => '18:30:00', 'end_time' => '19:15:00', 'label' => 'Messe du soir', 'is_available' => true]);
    }

    private function setting(string $key, string $value): void
    {
        Setting::find($key)->update(['value' => $value]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'intention_type' => 'Repos de l’âme', 'for_whom' => 'Feu Jean Kouassi', 'intention' => 'Pour le repos de son âme',
            'is_confidential' => false, 'formula' => 'single', 'date' => '2026-10-07',
            'time_slot_id' => $this->morning[3]->id, 'fullname' => 'Marie Kouassi', 'phone' => '+2250700000001',
            'email' => 'marie@example.com', 'will_attend' => true, 'reminder' => true,
            'offering' => 'free', 'amount' => 2000, 'payment_method' => 'secretariat',
        ];
    }

    public function test_availability_with_capacity_cancellation_and_delay(): void
    {
        $this->setting('mass.offering_amount', '2000');

        // Mercredi 7 : 4 intentions sur 5 → presque complet ; jeudi 8 : annulé ; vendredi 9 : complet
        foreach ([['2026-10-07', 3, 4], ['2026-10-09', 5, 5]] as [$date, $weekday, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $mess = Mess::create(['type' => 'x', 'fullname' => 'x', 'phone' => 'x', 'date_at' => $date, 'time_at' => '06:30:00', 'request_status' => 'pending']);
                MassSchedule::create(['mess_id' => $mess->id, 'date' => $date, 'time' => '06:30:00', 'time_slot_id' => $this->morning[$weekday]->id]);
            }
        }
        // Une demande annulée ne compte pas
        $canceled = Mess::create(['type' => 'x', 'fullname' => 'x', 'phone' => 'x', 'date_at' => '2026-10-10', 'time_at' => '06:30:00', 'request_status' => 'canceled']);
        MassSchedule::create(['mess_id' => $canceled->id, 'date' => '2026-10-10', 'time' => '06:30:00', 'time_slot_id' => $this->morning[6]->id]);

        ScheduleException::create(['date' => '2026-10-08', 'time_slot_id' => $this->morning[4]->id, 'is_cancelled' => true]);

        $data = $this->getJson('/api/mass-requests/availability?from=2026-10-05&days=6')->assertOk()->json('data');

        $this->assertSame(2000, $data['offering_amount']);
        $this->assertSame(0, $data['min_offering']);
        $this->assertSame(24, $data['min_delay_hours']);
        $this->assertCount(6, $data['days']);

        [$mon, $tue, $wed, $thu, $fri, $sat] = $data['days'];
        $this->assertSame(['2026-10-05', 1], [$mon['date'], $mon['weekday']]);
        $this->assertSame('too_late', $mon['slots'][0]['status']);
        $this->assertSame('too_late', $tue['slots'][0]['status']); // mardi 06:30 < maintenant + 24 h

        $this->assertCount(2, $wed['slots']); // 06:30 + 18:30 (créneau indisponible et adoration exclus)
        $this->assertSame([
            'time_slot_id' => $this->morning[3]->id, 'time' => '06:30', 'label' => 'Messe matinale',
            'capacity' => 5, 'taken' => 4, 'status' => 'almost_full',
        ], $wed['slots'][0]);
        $this->assertSame(['18:30', null, 'available'], [$wed['slots'][1]['time'], $wed['slots'][1]['capacity'], $wed['slots'][1]['status']]);

        $this->assertSame([], $thu['slots']);
        $this->assertSame('full', $fri['slots'][0]['status']);
        $this->assertSame(['available', 0], [$sat['slots'][0]['status'], $sat['slots'][0]['taken']]);

        $this->getJson('/api/mass-requests/availability?days=32')->assertUnprocessable();
    }

    public function test_single_request_secretariat_with_sequential_numbers(): void
    {
        $first = $this->postJson('/api/mass-requests', $this->payload())->assertCreated();
        $first->assertJsonPath('data.number', 'SSM-2026-0001')
            ->assertJsonPath('data.payment_status', 'to_pay')
            ->assertJsonPath('data.payment_method', 'secretariat')
            ->assertJsonPath('data.amount', 2000)
            ->assertJsonPath('data.wave_launch_url', null)
            ->assertJsonPath('data.schedules', [['date' => '2026-10-07', 'time' => '06:30', 'label' => 'Messe matinale', 'shifted' => false]]);
        $this->assertSame(40, strlen($first->json('data.access_token')));

        $this->postJson('/api/mass-requests', $this->payload())->assertCreated()->assertJsonPath('data.number', 'SSM-2026-0002');

        // Numéro repris après le plus grand de l'année (y compris supprimé), autres années ignorées
        Mess::create(['type' => 'x', 'fullname' => 'x', 'phone' => 'x', 'date_at' => '2026-10-07', 'time_at' => '06:30:00', 'number' => 'SSM-2026-0009'])->delete();
        Mess::create(['type' => 'x', 'fullname' => 'x', 'phone' => 'x', 'date_at' => '2025-10-07', 'time_at' => '06:30:00', 'number' => 'SSM-2025-0050']);
        $this->postJson('/api/mass-requests', $this->payload())->assertCreated()->assertJsonPath('data.number', 'SSM-2026-0010');

        $mess = Mess::where('number', 'SSM-2026-0001')->first();
        $this->assertSame('Repos de l’âme', $mess->intention_type);
        $this->assertSame('Repos de l’âme', $mess->type);
        $this->assertSame('Pour le repos de son âme', $mess->message);
        $this->assertSame('pending', $mess->request_status);
        $this->assertSame($this->morning[3]->id, $mess->time_slot_id);
        $this->assertFalse($mess->needs_review);
    }

    public function test_triduum_and_novena_shift_to_next_available_day(): void
    {
        // Jeudi 8 annulé, vendredi 9 complet
        ScheduleException::create(['date' => '2026-10-08', 'time_slot_id' => $this->morning[4]->id, 'is_cancelled' => true]);
        $this->morning[5]->update(['capacity' => 1]);
        $other = Mess::create(['type' => 'x', 'fullname' => 'x', 'phone' => 'x', 'date_at' => '2026-10-09', 'time_at' => '06:30:00']);
        MassSchedule::create(['mess_id' => $other->id, 'date' => '2026-10-09', 'time' => '06:30:00', 'time_slot_id' => $this->morning[5]->id]);

        $response = $this->postJson('/api/mass-requests', $this->payload(['formula' => 'triduum']))->assertCreated();
        $this->assertSame([
            ['date' => '2026-10-07', 'time' => '06:30', 'label' => 'Messe matinale', 'shifted' => false],
            ['date' => '2026-10-10', 'time' => '06:30', 'label' => 'Messe matinale', 'shifted' => true],
            ['date' => '2026-10-11', 'time' => '06:30', 'label' => 'Messe matinale', 'shifted' => false],
        ], $response->json('data.schedules'));

        $mess = Mess::where('number', $response->json('data.number'))->first();
        $this->assertTrue($mess->needs_review);
        $this->assertSame(3, $mess->masses_count);
        $this->assertSame('triduum', $mess->formula);

        // Neuvaine sans conflit : 9 jours consécutifs à 18:30 impossible (mercredi seulement) → 422
        $wednesdayEvening = TimeSlot::where('label', 'Messe du soir')->first();
        $this->postJson('/api/mass-requests', $this->payload(['formula' => 'novena', 'time_slot_id' => $wednesdayEvening->id]))
            ->assertCreated(); // décalée de semaine en semaine (≤ 30 jours)

        $novena = $this->postJson('/api/mass-requests', $this->payload(['formula' => 'novena', 'date' => '2026-10-12', 'time_slot_id' => $this->morning[1]->id]))
            ->assertCreated()->json('data.schedules');
        $this->assertCount(9, $novena);
        $this->assertSame('2026-10-20', $novena[8]['date']);
        $this->assertFalse(collect($novena)->contains('shifted', true));
    }

    public function test_business_rules_are_enforced(): void
    {
        // Délai minimal
        $this->postJson('/api/mass-requests', $this->payload(['date' => '2026-10-06', 'time_slot_id' => $this->morning[2]->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        // Créneau pas célébré ce jour-là (créneau du lundi un mercredi)
        $this->postJson('/api/mass-requests', $this->payload(['time_slot_id' => $this->morning[1]->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('time_slot_id');
        // Moyen de paiement pas encore disponible
        $this->postJson('/api/mass-requests', $this->payload(['payment_method' => 'orange']))
            ->assertUnprocessable()->assertJsonPath('errors.payment_method.0', 'Moyen de paiement bientôt disponible.');
        // Champs requis
        $this->postJson('/api/mass-requests', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['intention_type', 'for_whom', 'date', 'time_slot_id', 'fullname', 'phone', 'payment_method']);
        $this->postJson('/api/mass-requests', $this->payload(['intention' => str_repeat('a', 251)]))->assertUnprocessable();

        // Créneau complet
        $this->morning[3]->update(['capacity' => 1]);
        $this->postJson('/api/mass-requests', $this->payload())->assertCreated();
        $this->postJson('/api/mass-requests', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('time_slot_id');
        $this->assertSame(1, Mess::count());
    }

    public function test_offering_indicative_and_free(): void
    {
        // Sans offrande indicative : seule l'offrande libre est acceptée
        $this->postJson('/api/mass-requests', $this->payload(['offering' => 'indicative']))
            ->assertUnprocessable()->assertJsonValidationErrors('offering');

        $this->setting('mass.offering_amount', '2000');
        $this->setting('mass.min_offering', '1000');

        $this->postJson('/api/mass-requests', $this->payload(['offering' => 'indicative', 'formula' => 'triduum', 'amount' => 1]))
            ->assertCreated()->assertJsonPath('data.amount', 6000);

        // Par défaut (offrande indicative configurée) : indicative
        $payload = $this->payload();
        unset($payload['offering'], $payload['amount']);
        $this->postJson('/api/mass-requests', $payload)->assertCreated()->assertJsonPath('data.amount', 2000);

        $this->postJson('/api/mass-requests', $this->payload(['amount' => 500]))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/mass-requests', $this->payload(['amount' => 1500]))
            ->assertCreated()->assertJsonPath('data.amount', 1500);
    }

    public function test_wave_payment_and_webhook(): void
    {
        config(['services.wave.api_key' => 'wave_test_key', 'services.wave.frontend_url' => 'https://paroisse.test']);
        Http::fake(['api.wave.com/v1/checkout/sessions' => Http::response([
            'id' => 'cos-mass-1', 'wave_launch_url' => 'https://pay.wave.com/c/cos-mass-1', 'amount' => '2000', 'currency' => 'XOF',
        ], 200)]);

        $response = $this->postJson('/api/mass-requests', $this->payload(['payment_method' => 'wave']))
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.wave_launch_url', 'https://pay.wave.com/c/cos-mass-1');

        $number = $response->json('data.number');
        $token = $response->json('data.access_token');

        Http::assertSent(fn ($r) => $r['client_reference'] === $number
            && $r['amount'] === '2000'
            && $r['success_url'] === "https://paroisse.test/demande-messe/confirmation?n={$number}&t={$token}"
            && $r['error_url'] === "https://paroisse.test/demande-messe/erreur?n={$number}");

        $this->assertSame('cos-mass-1', Mess::where('number', $number)->value('wave_checkout_id'));

        // Webhook Wave (sans secret configuré) : la demande passe à « succeeded »
        $this->postJson('/api/wave/webhook', ['type' => 'checkout.session.completed', 'data' => [
            'id' => 'cos-mass-1', 'transaction_id' => 'T1', 'client_reference' => $number,
        ]])->assertOk();

        $mess = Mess::where('number', $number)->first();
        $this->assertSame('succeeded', $mess->payment_status);
        $this->assertSame('accepted', $mess->request_status);
    }

    public function test_wave_failure_cancels_request_and_frees_slot(): void
    {
        config(['services.wave.api_key' => 'wave_test_key']);
        Http::fake(['api.wave.com/*' => Http::response(['message' => 'boom'], 500)]);
        $this->morning[3]->update(['capacity' => 1]);

        $this->postJson('/api/mass-requests', $this->payload(['payment_method' => 'wave']))->assertStatus(502);
        $this->assertSame(0, Mess::count());

        // La place n'est pas consommée
        $this->postJson('/api/mass-requests', $this->payload())->assertCreated();
    }

    public function test_check_status_marks_request_paid(): void
    {
        config(['services.wave.api_key' => 'wave_test_key']);
        $this->postJson('/api/mass-requests', $this->payload())->assertCreated();
        Mess::first()->update(['payment_method' => 'wave', 'payment_status' => 'pending']);

        Http::fake(['api.wave.com/v1/checkout/sessions/cos-9' => Http::response([
            'checkout_status' => 'complete', 'payment_status' => 'succeeded', 'transaction_id' => 'T9',
            'amount' => '2000', 'client_reference' => 'SSM-2026-0001',
        ], 200)]);

        $this->getJson('/api/wave/checkout/cos-9/status')->assertOk()->assertJsonPath('payment_status', 'succeeded');
        $this->assertSame('succeeded', Mess::first()->payment_status);
    }

    public function test_recap_and_ics_require_token(): void
    {
        $data = $this->postJson('/api/mass-requests', $this->payload(['formula' => 'triduum', 'is_confidential' => true]))->json('data');

        $this->getJson("/api/mass-requests/{$data['number']}")->assertNotFound();
        $this->getJson("/api/mass-requests/{$data['number']}?t=faux")->assertNotFound();
        $this->getJson('/api/mass-requests/SSM-2026-9999?t=' . $data['access_token'])->assertNotFound();

        $this->getJson("/api/mass-requests/{$data['number']}?t={$data['access_token']}")->assertOk()
            ->assertJsonPath('data.number', $data['number'])
            ->assertJsonPath('data.intention_type', 'Repos de l’âme')
            ->assertJsonPath('data.for_whom', 'Feu Jean Kouassi')
            ->assertJsonPath('data.is_confidential', true)
            ->assertJsonPath('data.formula', 'triduum')
            ->assertJsonPath('data.fullname', 'Marie Kouassi')
            ->assertJsonPath('data.payment_status', 'to_pay')
            ->assertJsonCount(3, 'data.schedules')
            ->assertJsonStructure(['data' => ['created_at', 'amount', 'payment_method', 'wave_launch_url']]);

        $ics = $this->get("/api/mass-requests/{$data['number']}/ics?t={$data['access_token']}")->assertOk();
        $this->assertSame("attachment; filename=\"{$data['number']}.ics\"", $ics->headers->get('Content-Disposition'));
        $content = $ics->getContent();
        $this->assertSame(3, substr_count($content, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART;TZID=Africa/Abidjan:20261007T063000', $content);
        $this->assertStringContainsString('DTEND;TZID=Africa/Abidjan:20261007T071500', $content);
        $this->assertStringContainsString('SUMMARY:Messe (intention confidentielle)', $content);
        $this->get("/api/mass-requests/{$data['number']}/ics?t=faux")->assertNotFound();
    }

    public function test_celebrant_list_respects_confidentiality(): void
    {
        $this->postJson('/api/mass-requests', $this->payload())->assertCreated();
        $this->postJson('/api/mass-requests', $this->payload(['is_confidential' => true, 'for_whom' => 'Secret', 'intention' => 'Privé']))->assertCreated();
        $evening = TimeSlot::where('label', 'Messe du soir')->first();
        $this->postJson('/api/mass-requests', $this->payload(['time_slot_id' => $evening->id]))->assertCreated();
        // Une demande annulée n'apparaît pas
        $this->postJson('/api/mass-requests', $this->payload())->assertCreated();
        Mess::where('number', 'SSM-2026-0004')->update(['request_status' => 'canceled']);

        $this->getJson('/api/mass-schedules?date=2026-10-07')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $data = $this->getJson('/api/mass-schedules?date=2026-10-07')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertSame(['06:30', 'Messe matinale'], [$data[0]['time'], $data[0]['label']]);
        $this->assertSame([
            ['number' => 'SSM-2026-0001', 'intention_type' => 'Repos de l’âme', 'for_whom' => 'Feu Jean Kouassi', 'intention' => 'Pour le repos de son âme', 'payment_status' => 'to_pay'],
            ['number' => 'SSM-2026-0002', 'intention_type' => 'Repos de l’âme', 'for_whom' => 'Intention confidentielle', 'intention' => null, 'payment_status' => 'to_pay'],
        ], $data[0]['intentions']);
        $this->assertSame(['18:30', 'Messe du soir'], [$data[1]['time'], $data[1]['label']]);
        $this->assertCount(1, $data[1]['intentions']);

        // Admin messes existant : les nouveaux champs sont exposés
        $this->getJson('/api/messes')->assertOk()->assertJsonPath('data.0.number', 'SSM-2026-0004');
    }
}
