<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Matrice des rôles (contrat back-office §1) : au moins un 403 et un 200 par rôle.
 */
class BackOfficeRolesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->role($role)->create($attrs);
        Sanctum::actingAs($user);

        return $user;
    }

    private function assertForbidden($response): void
    {
        $response->assertForbidden()->assertExactJson(['error' => 'Accès refusé pour votre rôle.']);
    }

    public function test_admin_passes_everywhere(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/admin/integrations')->assertOk();
        $this->postJson('/api/announcements', ['title' => 'A', 'content' => 'x'])->assertCreated();
        $this->putJson('/api/church-project', ['goal_amount' => 1000])->assertOk();
    }

    public function test_priest(): void
    {
        $this->actingAsRole('priest');

        $this->postJson('/api/homilies', ['date' => '2026-10-01', 'title' => 'H', 'content' => 'x'])->assertCreated();
        $this->putJson('/api/settings', ['settings' => [['key' => 'pastor_word.message', 'value' => 'Bonjour']]])->assertOk();
        $this->assertForbidden($this->postJson('/api/announcements', ['title' => 'A', 'content' => 'x']));
        $this->assertForbidden($this->putJson('/api/settings', ['settings' => [['key' => 'parish.tagline', 'value' => 'x']]]));
        $this->assertForbidden($this->getJson('/api/users'));
    }

    public function test_secretariat(): void
    {
        $this->actingAsRole('secretariat');

        $this->postJson('/api/announcements', ['title' => 'A', 'content' => 'x'])->assertCreated();
        $this->postJson('/api/time-slots', ['type' => 'messe', 'weekday' => 1, 'start_time' => '06:30', 'end_time' => '07:00'])->assertCreated();
        $this->get('/api/donations/export')->assertOk();
        $this->assertForbidden($this->postJson('/api/publications', ['type' => 'text', 'title' => 'P']));
        $this->assertForbidden($this->putJson('/api/settings', ['settings' => [['key' => 'payment.aggregator', 'value' => 'x']]]));
    }

    public function test_communication(): void
    {
        $this->actingAsRole('communication');

        $this->postJson('/api/publications', ['type' => 'text', 'title' => 'P'])->assertCreated();
        $this->postJson('/api/history-milestones', ['year' => '1998', 'title' => 'Fondation'])->assertCreated();
        $this->assertForbidden($this->putJson('/api/church-project', ['goal_amount' => 1]));
        $this->assertForbidden($this->get('/api/donations/export'));
        $this->assertForbidden($this->getJson('/api/admin/integrations'));
    }

    public function test_treasurer(): void
    {
        $this->actingAsRole('treasurer');

        $this->putJson('/api/church-project', ['goal_amount' => 500000000])->assertOk();
        $this->postJson('/api/donations', [
            'donator' => 'X', 'amount' => 1000, 'project' => 'Nouvelle église', 'donation_at' => '2026-10-01',
        ])->assertCreated();
        $this->assertForbidden($this->postJson('/api/time-slots', ['type' => 'messe', 'weekday' => 1, 'start_time' => '06:30', 'end_time' => '07:00']));
        $this->assertForbidden($this->postJson('/api/homilies', ['date' => '2026-10-01', 'title' => 'H', 'content' => 'x']));
    }

    public function test_movement_leader_only_edits_own_service(): void
    {
        $own = Service::create(['title' => 'Chorale', 'description' => 'x']);
        $other = Service::create(['title' => 'Scouts', 'description' => 'x']);
        $this->actingAsRole('movement_leader', ['service_id' => $own->id]);

        $this->putJson("/api/services/{$own->id}", ['schedule' => 'Jeudi, 18:30'])->assertOk()->assertJsonPath('data.schedule', 'Jeudi, 18:30');
        $this->assertForbidden($this->putJson("/api/services/{$other->id}", ['schedule' => 'x']));
        $this->assertForbidden($this->postJson('/api/services', ['title' => 'Nouveau', 'description' => 'x']));
        $this->assertForbidden($this->postJson('/api/announcements', ['title' => 'A', 'content' => 'x']));
    }

    public function test_admin_reads_are_open_to_any_connected_user(): void
    {
        Announcement::create(['title' => 'Brouillon', 'content' => 'x', 'status' => 'draft']);
        $this->actingAsRole('movement_leader');

        $this->getJson('/api/announcements?all=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/messes')->assertOk();
        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->getJson('/api/admin/activities')->assertOk();
        $this->get('/api/subscriptions/export')->assertOk();
    }

    public function test_guests_are_rejected_and_me_exposes_role(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();

        $service = Service::create(['title' => 'Chorale', 'description' => 'x']);
        $this->actingAsRole('movement_leader', ['service_id' => $service->id, 'name' => 'Awa', 'fullname' => 'Awa K.']);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.role', 'movement_leader')
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.name', 'Awa')
            ->assertJsonPath('data.fullname', 'Awa K.')
            ->assertJsonStructure(['data' => ['email']]);
    }
}
