<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsFileAndAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_announcement_sheet_pdf(): void
    {
        Storage::fake('public');
        $pdf = UploadedFile::fake()->create('annonces.pdf', 200, 'application/pdf');

        $this->post('/api/settings/upload-file', ['key' => 'announcements.sheet_pdf', 'file' => $pdf], ['Accept' => 'application/json'])
            ->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/settings/upload-file', ['key' => 'announcements.sheet_pdf', 'file' => $pdf], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.key', 'announcements.sheet_pdf');

        $url = $response->json('data.value');
        $this->assertStringStartsWith(rtrim(env('APP_URL'), '/') . '/storage/documents/', $url);
        Storage::disk('public')->assertExists(str_replace('storage/', '', Setting::find('announcements.sheet_pdf')->value));

        // Exposé en URL absolue dans /settings et /settings/map
        $this->assertSame($url, $this->getJson('/api/settings/map')->json('data')['announcements.sheet_pdf']);
        $group = collect($this->getJson('/api/settings')->json('data.announcements'));
        $this->assertSame($url, $group->firstWhere('key', 'announcements.sheet_pdf')['value']);
        $this->assertSame('file', $group->firstWhere('key', 'announcements.sheet_pdf')['type']);

        // Clé d'un autre type, ou fichier non PDF : refusés
        $this->post('/api/settings/upload-file', ['key' => 'images.church_render', 'file' => $pdf], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->post('/api/settings/upload-file', [
            'key' => 'announcements.sheet_pdf', 'file' => UploadedFile::fake()->create('x.docx', 10, 'application/msword'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_new_settings_keys_exist(): void
    {
        $this->assertSame('textarea', Setting::find('parish.office_hours')->type);
        $this->assertSame('24', Setting::find('mass.min_delay_hours')->value);
        $this->assertSame('0', Setting::find('mass.min_offering')->value);
        $this->assertSame('', (string) Setting::find('mass.offering_amount')->value);
    }

    public function test_announcements_category_filter_and_per_page_cap(): void
    {
        Announcement::create(['title' => 'A', 'content' => 'x', 'category' => 'Sacrements']);
        Announcement::create(['title' => 'B', 'content' => 'x', 'category' => 'Liturgie']);

        $this->getJson('/api/announcements?category=Sacrements')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'A');
        $this->getJson('/api/announcements?per_page=100')->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/announcements?per_page=500')->assertJsonPath('meta.per_page', 100);
    }
}
