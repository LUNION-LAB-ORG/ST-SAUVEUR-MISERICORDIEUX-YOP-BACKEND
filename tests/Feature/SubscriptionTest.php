<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_consent_is_required(): void
    {
        $this->postJson('/api/subscriptions', ['phone' => '+225 07 00 00 00 01'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');

        $this->postJson('/api/subscriptions', ['phone' => '+225 07 00 00 00 01', 'consent' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');

        $this->assertSame(0, WhatsappSubscriber::count());
    }

    public function test_subscribe_normalizes_phone_and_defaults_lists(): void
    {
        $this->postJson('/api/subscriptions', ['phone' => '+225 07-00.00 (00) 01', 'consent' => true])
            ->assertCreated()
            ->assertJsonPath('data.phone', '+2250700000001')
            ->assertJsonPath('data.lists', ['parole', 'annonces'])
            ->assertJsonPath('data.unsubscribed_at', null);

        $this->assertNotNull(WhatsappSubscriber::first()->consented_at);

        $this->postJson('/api/subscriptions', ['phone' => 'abc', 'consent' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_unsubscribe_then_resubscribe(): void
    {
        $this->postJson('/api/subscriptions', ['phone' => '+2250700000001', 'consent' => true])->assertCreated();

        $this->deleteJson('/api/subscriptions', ['phone' => '+225 07 00 00 00 01'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+2250700000001');
        $this->assertNotNull(WhatsappSubscriber::first()->unsubscribed_at);

        $this->deleteJson('/api/subscriptions', ['phone' => '+2250799999999'])->assertNotFound();

        $this->postJson('/api/subscriptions', ['phone' => '+2250700000001', 'lists' => ['parole'], 'consent' => '1'])
            ->assertCreated()
            ->assertJsonPath('data.unsubscribed_at', null)
            ->assertJsonPath('data.lists', ['parole']);

        $this->assertSame(1, WhatsappSubscriber::count());
    }

    public function test_admin_list_and_export(): void
    {
        $this->postJson('/api/subscriptions', ['phone' => '+2250700000001', 'consent' => true]);
        $this->postJson('/api/subscriptions', ['phone' => '+2250700000002', 'consent' => true]);
        $this->deleteJson('/api/subscriptions', ['phone' => '+2250700000002']);

        $this->getJson('/api/subscriptions')->assertUnauthorized();
        $this->getJson('/api/subscriptions/export')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta' => ['per_page', 'total']])
            ->assertJsonPath('meta.per_page', 15);

        $this->getJson('/api/subscriptions?status=active')->assertJsonCount(1, 'data');

        $response = $this->get('/api/subscriptions/export')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertSame('phone,lists,consented_at,unsubscribed_at', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('+2250700000001,"parole,annonces",', $lines[1]);
    }
}
