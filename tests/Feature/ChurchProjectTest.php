<?php

namespace Tests\Feature;

use App\Models\ChurchProject;
use App\Models\Donation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChurchProjectTest extends TestCase
{
    use RefreshDatabase;

    private function donation(string $project, float $amount, string $status): void
    {
        Donation::create([
            'donator' => 'Fidèle', 'amount' => $amount, 'project' => $project,
            'paymethod' => 'wave', 'payment_status' => $status, 'donation_at' => now(),
        ]);
    }

    public function test_default_row_is_created_by_migration(): void
    {
        $this->assertSame(1, ChurchProject::count());

        $this->getJson('/api/church-project')
            ->assertOk()
            ->assertJsonPath('data.title', 'Construction de la nouvelle église')
            ->assertJsonPath('data.goal_amount', 0)
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.phases.0', ['name' => 'Études et permis', 'status' => 'upcoming'])
            ->assertJsonPath('data.phases.4.name', 'Finitions')
            ->assertJsonPath('data.gallery', [])
            ->assertJsonPath('data.image', null);
    }

    public function test_collected_amount_and_progress(): void
    {
        ChurchProject::current()->update(['goal_amount' => 500000000, 'adjustment_amount' => 10000000]);

        $this->donation('Nouvelle église', 150000000, 'succeeded');
        $this->donation('Nouvelle église', 30000000, 'succeeded');
        $this->donation('Nouvelle église', 99000000, 'pending');   // ignoré
        $this->donation('Nouvelle église', 99000000, 'failed');    // ignoré
        $this->donation('Fonctionnement', 99000000, 'succeeded');  // autre projet

        $this->getJson('/api/church-project')
            ->assertOk()
            ->assertJsonPath('data.collected_amount', 190000000)
            ->assertJsonPath('data.adjustment_amount', 10000000)
            ->assertJsonPath('data.progress', 38);
    }

    public function test_project_label_setting_and_progress_cap(): void
    {
        Setting::find('donation.project_label')->update(['value' => 'Église 2030']);
        ChurchProject::current()->update(['goal_amount' => 1000]);

        $this->donation('Église 2030', 5000, 'succeeded');
        $this->donation('Nouvelle église', 999, 'succeeded');

        $this->getJson('/api/church-project')
            ->assertJsonPath('data.collected_amount', 5000)
            ->assertJsonPath('data.progress', 100);
    }

    public function test_admin_update_and_gallery(): void
    {
        Storage::fake('public');

        $this->putJson('/api/church-project', ['goal_amount' => 1])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/church-project', [
            'presentation' => 'Un projet pour tous',
            'goal_amount'  => 500000000,
            'phases'       => [
                ['name' => 'Études et permis', 'status' => 'done'],
                ['name' => 'Fondations', 'status' => 'in_progress'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.presentation', 'Un projet pour tous')
            ->assertJsonPath('data.goal_amount', 500000000)
            ->assertJsonPath('data.phases.0.status', 'done')
            ->assertJsonCount(2, 'data.phases');

        $this->putJson('/api/church-project', ['phases' => [['name' => 'X', 'status' => 'invalide']]])
            ->assertUnprocessable();

        $this->post('/api/church-project/gallery', ['image' => UploadedFile::fake()->create('a.jpg', 20, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(1, 'data.gallery');
        $response = $this->post('/api/church-project/gallery', ['image' => UploadedFile::fake()->create('b.jpg', 20, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(2, 'data.gallery');

        $this->assertStringStartsWith(rtrim(env('APP_URL'), '/') . '/storage/church/', $response->json('data.gallery.0'));

        $first = ChurchProject::current()->gallery[0];
        Storage::disk('public')->assertExists(str_replace('storage/', '', $first));

        $this->deleteJson('/api/church-project/gallery/0')->assertOk()->assertJsonCount(1, 'data.gallery');
        Storage::disk('public')->assertMissing(str_replace('storage/', '', $first));
        $this->deleteJson('/api/church-project/gallery/5')->assertNotFound();
    }
}
