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

        // La page d'accueil / agenda répond avec les données de démonstration
        $this->getJson('/api/events?upcoming=1')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/publications?featured=1')->assertOk()->assertJsonPath('data.0.type', 'video');
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
