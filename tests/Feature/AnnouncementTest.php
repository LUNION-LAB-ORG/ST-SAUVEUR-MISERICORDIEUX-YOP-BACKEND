<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-29 12:00:00', 'Africa/Abidjan'));
    }

    private function make(string $title, array $attrs = []): Announcement
    {
        return Announcement::create($attrs + ['title' => $title, 'content' => 'Contenu', 'category' => 'Vie paroissiale']);
    }

    public function test_public_index_applies_visibility_window_and_ordering(): void
    {
        $this->make('Sans fenêtre', ['sort_order' => 1]);
        $this->make('Commence aujourd’hui', ['visible_from' => '2026-09-29', 'sort_order' => 2]);
        $this->make('Finit aujourd’hui', ['visible_until' => '2026-09-29', 'sort_order' => 3]);
        $this->make('À la une', ['is_featured' => true, 'sort_order' => 9]);
        $this->make('Future', ['visible_from' => '2026-09-30']);
        $this->make('Expirée', ['visible_until' => '2026-09-28']);
        $this->make('Brouillon', ['status' => 'draft']);

        $response = $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 15);

        $this->assertSame(
            ['À la une', 'Sans fenêtre', 'Commence aujourd’hui', 'Finit aujourd’hui'],
            array_column($response->json('data'), 'title')
        );

        $this->getJson('/api/announcements?featured=1')->assertJsonCount(1, 'data');
        $this->getJson('/api/announcements?category=Autre')->assertJsonCount(0, 'data');
    }

    public function test_show_respects_window_and_admin_sees_all(): void
    {
        $future = $this->make('Future', ['visible_from' => '2026-10-10']);
        $visible = $this->make('Visible', ['visible_until' => '2026-12-31']);

        $this->getJson("/api/announcements/{$future->id}")->assertNotFound();
        $this->getJson("/api/announcements/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.visible_until', '2026-12-31')
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonStructure(['data' => [
                'id', 'category', 'title', 'content', 'contact', 'is_featured',
                'visible_from', 'visible_until', 'status', 'sort_order', 'created_at',
            ]]);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/announcements?all=1')->assertJsonCount(2, 'data');
        $this->getJson("/api/announcements/{$future->id}")->assertOk();

        $this->postJson('/api/announcements', [
            'title' => 'Kermesse', 'content' => 'Samedi', 'is_featured' => true, 'visible_from' => '2026-09-01',
        ])->assertCreated()->assertJsonPath('data.is_featured', true)->assertJsonPath('data.status', 'published');
    }
}
