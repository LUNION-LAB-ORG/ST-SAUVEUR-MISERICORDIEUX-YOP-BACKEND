<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Council;
use App\Models\Event;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\Setting;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Database\Seeders\DemoAccueilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoAccueilSeederTest extends TestCase
{
    use RefreshDatabase;

    private function counts(): array
    {
        return collect([
            'time_slots', 'priests', 'homilies', 'services', 'history_milestones', 'announcements',
            'events', 'publications', 'publication_comments', 'councils', 'schedule_exceptions',
            'users', 'messes', 'mass_schedules', 'donations', 'listens', 'whatsapp_subscribers', 'activity_logs',
        ])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    public function test_seeder_is_idempotent_and_fills_subpages(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'Africa/Abidjan'));

        $this->seed(DemoAccueilSeeder::class);
        $first = $this->counts();
        $settings = Setting::allAsMap();

        $this->seed(DemoAccueilSeeder::class);
        $this->assertSame($first, $this->counts());
        $this->assertSame($settings, Setting::allAsMap());

        $this->assertSame(6, Publication::count());
        $video = Publication::where('type', 'video')->where('is_featured', true)->first();
        $this->assertNotNull($video);
        $this->assertNull($video->video_url);
        $this->assertSame(2, PublicationComment::where('publication_id', $video->id)->where('status', 'published')->count());

        $this->assertSame(5, Council::count());
        $this->assertSame(6, Announcement::count());
        $this->assertEqualsCanonicalizing(['Sacrements', 'Liturgie', 'Chantier', 'Vie paroissiale'], Announcement::distinct()->pluck('category')->all());

        $this->assertSame(4, Event::count());
        $demo = Event::where('slug', 'messe-douverture-de-lannee-pastorale-et-journee-paroissiale')->first();
        $this->assertNotNull($demo);
        $this->assertSame('2026-10-11', $demo->dateString());
        $this->assertSame('16:00', Event::hhmm($demo->getRawOriginal('end_time')));
        $this->assertNotEmpty($demo->programme);
        $this->assertSame('Toute la communauté', $demo->audience);

        $this->assertSame(0, TimeSlot::where('type', 'messe')->where(fn ($q) => $q->whereNull('capacity')->orWhere('capacity', '!=', 10))->count());
        $this->assertSame('2000', Setting::find('mass.offering_amount')->value);
        $this->assertSame('Lundi au samedi, 8 h – 12 h et 15 h – 18 h', Setting::find('parish.office_hours')->value);

        // Back-office (lot 3)
        $this->assertEqualsCanonicalizing(
            ['secretariat', 'priest', 'communication', 'treasurer', 'movement_leader'],
            \App\Models\User::where('email', 'like', '%@demo.local')->pluck('role')->all()
        );
        $leader = \App\Models\User::where('email', 'mouvement@demo.local')->first();
        $this->assertNotNull($leader->service_id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $leader->password));
        $this->postJson('/api/auth/login', ['email' => 'pretre@demo.local', 'password' => 'password123'])->assertOk()->assertJsonPath('data.role', 'priest');

        $this->assertTrue(\App\Models\Mess::where('request_status', 'pending')->exists());
        $this->assertTrue(\App\Models\Mess::where('payment_status', 'to_pay')->exists());
        $this->assertTrue(\App\Models\Mess::where('payment_status', 'succeeded')->exists());
        $this->assertSame(1, \App\Models\Mess::where('is_confidential', true)->count());
        $this->assertSame(4, \App\Models\Donation::where('payment_status', 'succeeded')->count());
        $this->assertSame(2, \App\Models\Listen::where('request_status', 'pending')->count());
        $this->assertSame(4, PublicationComment::where('status', 'pending')->count());
        $this->assertSame(3, \App\Models\WhatsappSubscriber::count());
        $this->assertSame(0, DB::table('activity_logs')->count());

        $dashboard = $this->actingAs($leader, 'sanctum')->getJson('/api/admin/dashboard')->assertOk()->json('data');
        $this->assertSame(4, $dashboard['comments']['pending']);
        $this->assertSame(2, $dashboard['listens']['pending']);
        $this->assertGreaterThan(0, $dashboard['donations_week']['count']);

        // La page d'accueil / agenda répond avec les données de démonstration
        $this->getJson('/api/events?upcoming=1')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/publications?featured=1')->assertOk()->assertJsonPath('data.0.type', 'video');
    }

    public function test_no_demo_accounts_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->app->make(DemoAccueilSeeder::class)->run();
        $this->app['env'] = 'testing';

        $this->assertSame(0, \App\Models\User::where('email', 'like', '%@demo.local')->count());
    }

    public function test_seeder_never_overwrites_real_content(): void
    {
        Setting::find('mass.offering_amount')->update(['value' => '5000']);
        Announcement::create(['title' => 'Annonce réelle', 'content' => 'x']);
        Event::create(['title' => 'Vrai événement', 'date_at' => now()->addDays(3)->toDateString(), 'time_at' => '10:00', 'location_at' => 'Église']);

        $this->seed(DemoAccueilSeeder::class);

        $this->assertSame('5000', Setting::find('mass.offering_amount')->value);
        $this->assertSame(1, Announcement::count());
        $this->assertSame(1, Event::count());
    }
}
