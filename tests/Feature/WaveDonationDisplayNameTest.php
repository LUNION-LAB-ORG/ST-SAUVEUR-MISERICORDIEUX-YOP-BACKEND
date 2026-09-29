<?php

namespace Tests\Feature;

use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WaveDonationDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_wave_checkout_stores_display_name(): void
    {
        config(['services.wave.api_key' => 'wave_test_key']);

        Http::fake([
            'api.wave.com/v1/checkout/sessions' => Http::response([
                'id'              => 'cos-test-123',
                'wave_launch_url' => 'https://pay.wave.com/c/cos-test-123',
                'amount'          => '10000',
                'currency'        => 'XOF',
                'when_expires'    => '2026-09-29T12:30:00Z',
            ], 200),
        ]);

        $this->postJson('/api/wave/checkout', [
            'amount'       => 10000,
            'type'         => 'donation',
            'donator'      => 'Marie K.',
            'project'      => 'Nouvelle église',
            'display_name' => true,
        ])->assertOk()->assertJsonPath('checkout_id', 'cos-test-123');

        $donation = Donation::where('paytransaction', 'cos-test-123')->first();
        $this->assertNotNull($donation);
        $this->assertTrue($donation->display_name);
        $this->assertSame('pending', $donation->payment_status);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer wave_test_key'));
    }

    public function test_display_name_defaults_to_false_and_public_store_accepts_it(): void
    {
        config(['services.wave.api_key' => 'wave_test_key']);
        Http::fake(['api.wave.com/*' => Http::response([
            'id' => 'cos-2', 'wave_launch_url' => 'https://pay.wave.com/c/cos-2', 'amount' => '5000', 'currency' => 'XOF',
        ], 200)]);

        $this->postJson('/api/wave/checkout', ['amount' => 5000, 'type' => 'donation'])->assertOk();
        $this->assertFalse(Donation::where('paytransaction', 'cos-2')->first()->display_name);

        $this->postJson('/api/donations/public', [
            'donator' => 'Paul', 'amount' => 2000, 'project' => 'Nouvelle église', 'display_name' => true,
        ])->assertCreated()->assertJsonPath('data.display_name', true);

        $this->postJson('/api/wave/checkout', ['amount' => 5000, 'type' => 'donation', 'display_name' => 'peut-être'])
            ->assertUnprocessable();
    }
}
