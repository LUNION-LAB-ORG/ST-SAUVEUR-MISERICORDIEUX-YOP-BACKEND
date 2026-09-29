<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mouvements et groupes (table `services`) : champs de la refonte et publication.
 */
class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $title, array $attrs = []): Service
    {
        return Service::create($attrs + ['title' => $title, 'description' => 'Résumé']);
    }

    public function test_existing_rows_stay_published_after_migration(): void
    {
        $migration = require database_path('migrations/2026_09_29_000013_alter_services_add_movement_fields.php');

        // Simule la prod : ligne créée avant l'ajout des colonnes
        $migration->down();
        DB::table('services')->insert([
            'title' => 'Chorale Sainte Cécile', 'description' => 'Chant liturgique',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration->up();

        $row = DB::table('services')->first();
        $this->assertSame('published', $row->status);
        $this->assertEquals(0, $row->sort_order);
        $this->assertNull($row->category);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'published');
    }

    public function test_public_index_only_published_sorted_and_paginated(): void
    {
        $this->service('Chorale', ['sort_order' => 2]);
        $this->service('Légion de Marie', ['sort_order' => 1]);
        $this->service('Scouts', ['sort_order' => 1]);
        $this->service('Brouillon', ['status' => 'draft']);
        $this->service('Masqué', ['status' => 'hidden']);

        $response = $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta' => ['per_page', 'total']])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 15);

        $this->assertSame(['Légion de Marie', 'Scouts', 'Chorale'], array_column($response->json('data'), 'title'));

        // ?all=1 sans authentification : ignoré
        $this->getJson('/api/services?all=1')->assertJsonPath('meta.total', 3);

        // Compatibilité sort_by / sort_dir
        $titles = array_column($this->getJson('/api/services?sort_by=id&sort_dir=desc')->json('data'), 'title');
        $this->assertSame(['Scouts', 'Légion de Marie', 'Chorale'], $titles);

        // Colonne de tri inconnue : tri par défaut
        $titles = array_column($this->getJson('/api/services?sort_by=inconnue')->json('data'), 'title');
        $this->assertSame(['Légion de Marie', 'Scouts', 'Chorale'], $titles);

        // per_page accepté jusqu'à 100
        $this->getJson('/api/services?per_page=100')->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/services?per_page=500')->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/services?per_page=2')->assertJsonCount(2, 'data');
    }

    public function test_admin_all_and_show_visibility(): void
    {
        $published = $this->service('Chorale');
        $draft = $this->service('Brouillon', ['status' => 'draft']);

        $this->getJson("/api/services/{$published->id}")->assertOk();
        $this->getJson("/api/services/{$draft->id}")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/services')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/services?all=1')->assertJsonPath('meta.total', 2);
        $this->getJson("/api/services/{$draft->id}")->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_create_and_update_new_fields(): void
    {
        $this->postJson('/api/services', ['title' => 'X', 'description' => 'Y'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/services', [
            'title'       => 'Jeunesse Étudiante Catholique',
            'description' => 'Résumé court',
            'content'     => 'Présentation longue',
            'leader'      => 'Awa K.',
            'schedule'    => 'Samedi, 16:00',
            'category'    => 'Jeunesse',
            'audience'    => '15-25 ans',
            'location'    => 'Salle paroissiale',
            'whatsapp'    => '+2250700000000',
            'sort_order'  => 3,
        ])->assertCreated()
            ->assertJsonPath('data.category', 'Jeunesse')
            ->assertJsonPath('data.audience', '15-25 ans')
            ->assertJsonPath('data.location', 'Salle paroissiale')
            ->assertJsonPath('data.whatsapp', '+2250700000000')
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.leader', 'Awa K.')
            ->assertJsonPath('data.schedule', 'Samedi, 16:00')
            ->json('data.id');

        $this->assertSame('published', Service::find($id)->status);

        $this->putJson("/api/services/{$id}", ['status' => 'hidden', 'category' => 'Charité', 'sort_order' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden')
            ->assertJsonPath('data.category', 'Charité')
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.title', 'Jeunesse Étudiante Catholique');

        $this->putJson("/api/services/{$id}", ['status' => 'archived'])->assertUnprocessable();
        $this->putJson("/api/services/{$id}", ['whatsapp' => str_repeat('1', 31)])->assertUnprocessable();
        $this->putJson("/api/services/{$id}", ['sort_order' => 'abc'])->assertUnprocessable();
    }
}
