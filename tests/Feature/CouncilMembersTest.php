<?php

namespace Tests\Feature;

use App\Models\Council;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouncilMembersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_replaces_members_and_phones_stay_private(): void
    {
        $this->admin();
        $id = $this->postJson('/api/councils', [
            'name'    => 'Conseil pastoral paroissial',
            'status'  => 'published',
            'members' => [
                ['name' => 'Membre Un', 'function' => 'Président', 'phone' => '0700000001'],
                ['name' => 'Membre Deux', 'function' => 'Secrétaire', 'phone' => null],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.members.0.phone', '0700000001')
            ->json('data.id');

        $this->putJson("/api/councils/{$id}", ['members' => [
            ['name' => 'Membre Deux', 'function' => 'Secrétaire'],
            ['name' => 'Membre Trois', 'function' => 'Membre', 'phone' => '+225 07 00 00 00 03'],
        ]])->assertOk()
            ->assertJsonPath('data.members.0.name', 'Membre Deux')
            ->assertJsonPath('data.members.1.phone', '+225 07 00 00 00 03');
        $this->assertSame(2, Council::find($id)->members()->count());

        // Une modification sans « members » ne touche pas la liste
        $this->putJson("/api/councils/{$id}", ['role' => 'Conduite pastorale'])->assertOk();
        $this->assertSame(2, Council::find($id)->members()->count());

        // Site public : noms et fonctions, jamais les téléphones
        $this->app['auth']->forgetGuards();
        $public = $this->getJson('/api/councils')->assertOk()->json('data.0.members');
        $this->assertSame(['name' => 'Membre Deux', 'function' => 'Secrétaire'], $public[0]);
        $this->assertStringNotContainsString('00 03', $this->getJson('/api/councils')->getContent());
    }

    public function test_members_validation_and_roles(): void
    {
        $this->admin();
        $this->postJson('/api/councils', ['name' => 'X', 'members' => [['function' => 'Sans nom']]])->assertUnprocessable();
        $this->postJson('/api/councils', ['name' => 'X', 'members' => [['name' => 'A', 'phone' => 'appelez-moi']]])->assertUnprocessable();

        $this->admin('communication');
        $this->postJson('/api/councils', ['name' => 'X'])->assertForbidden();
    }
}
