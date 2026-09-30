<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackOfficeAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password = 'password123')
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_login_returns_role_and_records_last_login(): void
    {
        $user = User::factory()->role('secretariat')->create(['email' => 's@x.ci', 'password' => Hash::make('password123')]);

        $this->login('s@x.ci')->assertOk()
            ->assertJsonPath('data.role', 'secretariat')
            ->assertJsonPath('data.service_id', null)
            ->assertJsonStructure(['token']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_admin_creates_account_and_inactive_account_cannot_log_in(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/users', [
            'fullname' => 'Trésorier', 'email' => 't@x.ci', 'phone' => '+2250100000001',
            'password' => 'motdepasse', 'role' => 'treasurer',
        ])->assertCreated()->assertJsonPath('data.role', 'treasurer')->json('data.id');

        $this->postJson('/api/users', ['fullname' => 'X', 'phone' => '+2250100000009', 'password' => 'motdepasse', 'role' => 'superadmin'])
            ->assertUnprocessable();

        $token = $this->login('t@x.ci', 'motdepasse')->assertOk()->json('token');
        $this->assertSame(1, User::find($id)->tokens()->count());

        $this->putJson("/api/users/{$id}", ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertSame(0, User::find($id)->tokens()->count());

        $this->login('t@x.ci', 'motdepasse')->assertForbidden();

        // L'ancien jeton ne fonctionne plus
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_last_active_admin_is_protected(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}", ['status' => 'inactive'])->assertUnprocessable();
        $this->putJson("/api/users/{$admin->id}", ['role' => 'priest'])->assertUnprocessable();
        $this->assertSame('admin', $admin->fresh()->role);

        // Avec un second admin actif, le rôle peut être retiré (mais pas l'auto-désactivation)
        $other = User::factory()->create();
        $this->putJson("/api/users/{$admin->id}", ['status' => 'inactive'])->assertUnprocessable();
        $this->putJson("/api/users/{$other->id}", ['role' => 'communication'])->assertOk();
        $this->putJson("/api/users/{$admin->id}", ['role' => 'priest'])->assertUnprocessable();
        $this->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();
    }

    public function test_public_registration_never_grants_back_office_access(): void
    {
        User::factory()->create();

        $this->postJson('/api/auth/register', [
            'fullname' => 'Visiteur', 'email' => 'v@x.ci', 'phone' => '+2250100000002',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse', 'role' => 'ADMIN',
        ])->assertCreated()->assertJsonMissingPath('token');

        $visitor = User::where('email', 'v@x.ci')->first();
        $this->assertSame('inactive', $visitor->status);
        $this->assertNotSame('admin', $visitor->role);
        $this->login('v@x.ci', 'motdepasse')->assertForbidden();
    }
}
