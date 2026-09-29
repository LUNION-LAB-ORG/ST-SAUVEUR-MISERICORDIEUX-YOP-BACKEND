<?php

namespace Tests\Feature;

use App\Models\HistoryMilestone;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function seedPriests(): array
    {
        return [
            'b'      => Priest::create(['fullname' => 'Père Vicaire', 'function' => 'Vicaire', 'sort_order' => 2]),
            'a'      => Priest::create(['fullname' => 'Père Curé', 'function' => 'Curé', 'sort_order' => 1]),
            'draft'  => Priest::create(['fullname' => 'Brouillon', 'function' => 'Vicaire', 'status' => 'draft']),
            'hidden' => Priest::create(['fullname' => 'Masqué', 'function' => 'Père résident', 'status' => 'hidden']),
        ];
    }

    public function test_public_index_returns_only_published_sorted(): void
    {
        $this->seedPriests();

        $this->getJson('/api/priests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.fullname', 'Père Curé')
            ->assertJsonPath('data.1.fullname', 'Père Vicaire')
            ->assertJsonPath('data.0.status', 'published');

        // ?all=1 sans authentification : ignoré
        $this->getJson('/api/priests?all=1')->assertJsonCount(2, 'data');
    }

    public function test_admin_can_list_all_statuses(): void
    {
        $this->seedPriests();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/priests')->assertJsonCount(2, 'data');
        $this->getJson('/api/priests?all=1')->assertJsonCount(4, 'data');
    }

    public function test_show_hides_unpublished_from_public(): void
    {
        $m = $this->seedPriests();

        $this->getJson("/api/priests/{$m['a']->id}")->assertOk()->assertJsonPath('data.fullname', 'Père Curé');
        $this->getJson("/api/priests/{$m['draft']->id}")->assertNotFound();
        $this->getJson('/api/priests/9999')->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/priests/{$m['draft']->id}")->assertOk();
    }

    public function test_mutations_require_auth_and_handle_photo(): void
    {
        Storage::fake('public');

        $this->postJson('/api/priests', ['fullname' => 'Père A', 'function' => 'Curé'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/priests', [
            'fullname' => 'Père A', 'function' => 'Curé', 'missions' => 'Pastorale',
            'ordination_year' => 2001, 'photo' => UploadedFile::fake()->create('p.jpg', 20, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.ordination_year', 2001);

        $this->assertStringStartsWith(rtrim(env('APP_URL'), '/') . '/storage/priests/', $response->json('data.photo'));
        $id = $response->json('data.id');

        $this->putJson("/api/priests/{$id}", ['status' => 'hidden'])->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->putJson("/api/priests/{$id}", ['status' => 'archived'])->assertUnprocessable();

        $this->deleteJson("/api/priests/{$id}")->assertNoContent();
        $this->assertSoftDeleted('priests', ['id' => $id]);
        $this->assertSame(0, Priest::count());
    }

    public function test_history_milestones_and_homilies(): void
    {
        HistoryMilestone::create(['year' => '2005', 'title' => 'Érection', 'sort_order' => 2]);
        HistoryMilestone::create(['year' => '1998', 'title' => 'Fondation', 'sort_order' => 1]);
        HistoryMilestone::create(['year' => '2020', 'title' => 'Brouillon', 'status' => 'draft']);

        $this->getJson('/api/history-milestones')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['id' => 2, 'year' => '1998', 'title' => 'Fondation', 'status' => 'published', 'sort_order' => 1]);

        Sanctum::actingAs(User::factory()->create());
        $priest = Priest::create(['fullname' => 'Père B', 'function' => 'Vicaire']);

        $this->postJson('/api/homilies', [
            'date' => '2026-09-29', 'priest_id' => $priest->id, 'title' => 'Homélie', 'content' => 'Texte',
        ])->assertCreated()
            ->assertJsonPath('data.priest', ['id' => $priest->id, 'fullname' => 'Père B', 'function' => 'Vicaire'])
            ->assertJsonPath('data.status', 'published');

        $this->postJson('/api/homilies', ['date' => '2026-09-30', 'title' => 'Autre', 'content' => 'x', 'status' => 'draft'])
            ->assertCreated()->assertJsonPath('data.priest', null);

        $this->getJson('/api/homilies?date=2026-09-29')->assertJsonCount(1, 'data');
        $this->getJson('/api/homilies?all=1')->assertJsonCount(2, 'data');
    }
}
