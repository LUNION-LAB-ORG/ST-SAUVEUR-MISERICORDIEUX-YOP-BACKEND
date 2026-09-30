<?php

namespace Tests\Feature;

use App\Models\Council;
use App\Models\Listen;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PastoralTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_councils_publication_and_crud(): void
    {
        Council::create(['name' => 'Conseil économique', 'leader_title' => 'Président', 'sort_order' => 2]);
        Council::create(['name' => 'Conseil pastoral', 'role' => 'Orientations', 'leader_title' => 'Coordinateur', 'leader_name' => 'Awa K.', 'sort_order' => 1]);
        $draft = Council::create(['name' => 'Brouillon', 'status' => 'draft']);

        $this->getJson('/api/councils')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => 2, 'name' => 'Conseil pastoral', 'role' => 'Orientations', 'leader_title' => 'Coordinateur',
                'leader_name' => 'Awa K.', 'status' => 'published', 'sort_order' => 1, 'members' => [],
            ]);
        $this->getJson("/api/councils/{$draft->id}")->assertNotFound();
        $this->postJson('/api/councils', ['name' => 'X'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/councils?all=1')->assertJsonCount(3, 'data');
        $id = $this->postJson('/api/councils', ['name' => 'Équipe liturgique', 'leader_title' => 'Responsable'])
            ->assertCreated()->assertJsonPath('data.status', 'published')->json('data.id');
        $this->putJson("/api/councils/{$id}", ['leader_name' => 'Jean'])->assertOk()->assertJsonPath('data.leader_name', 'Jean');
        $this->deleteJson("/api/councils/{$id}")->assertNoContent();
    }

    public function test_priests_expose_since_year_and_congregation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/priests', [
            'fullname' => 'Père Paul', 'function' => 'Curé', 'since_year' => 2021, 'congregation' => 'Archidiocèse d’Abidjan',
        ])->assertCreated()
            ->assertJsonPath('data.since_year', 2021)
            ->assertJsonPath('data.congregation', 'Archidiocèse d’Abidjan');
    }

    public function test_listen_with_priest_and_optional_message(): void
    {
        $priest = Priest::create(['fullname' => 'Père Paul', 'function' => 'Curé']);

        $this->postJson('/api/listens', [
            'type' => 'Accompagnement spirituel', 'fullname' => 'Marie', 'phone' => '+2250700000001', 'priest_id' => $priest->id,
        ])->assertCreated()
            ->assertJsonPath('data.message', null)
            ->assertJsonPath('data.priest', ['id' => $priest->id, 'fullname' => 'Père Paul', 'function' => 'Curé']);

        // Sans prêtre, avec message : comportement existant
        $this->postJson('/api/listens', ['type' => 'Confession', 'fullname' => 'Jean', 'message' => 'Bonjour'])
            ->assertCreated()->assertJsonPath('data.priest', null)->assertJsonPath('data.message', 'Bonjour');

        $this->postJson('/api/listens', ['fullname' => 'X', 'priest_id' => 999])->assertUnprocessable();

        // Admin « Écoutes » : liste avec le prêtre
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/listens')->assertOk()->assertJsonPath('meta.total', 2);
        $listen = Listen::where('fullname', 'Marie')->first();
        $this->getJson("/api/listens/{$listen->id}")->assertJsonPath('data.priest.fullname', 'Père Paul');
    }

    public function test_pastors_history_listing_and_optional_description(): void
    {
        $this->postJson('/api/pastors', ['fullname' => 'X', 'started_at' => '2001-01-01'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'communication', 'status' => 'active']));
        $this->postJson('/api/pastors', ['fullname' => 'Père Deux', 'started_at' => '2012-09-01', 'ended_at' => '2020-08-31'])->assertCreated();
        $premier = $this->postJson('/api/pastors', ['fullname' => 'Père Un', 'started_at' => '1998-09-01', 'ended_at' => '2012-08-31', 'description' => 'Fondateur'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/pastors/{$premier}", ['_method' => 'PUT', 'description' => null])->assertOk()->assertJsonPath('data.description', '');

        $this->app['auth']->forgetGuards();
        $noms = collect($this->getJson('/api/pastors?per_page=100&sort_by=started_at&sort_dir=asc')->assertOk()->json('data'))->pluck('fullname')->all();
        $this->assertSame(['Père Un', 'Père Deux'], $noms);
    }
}
