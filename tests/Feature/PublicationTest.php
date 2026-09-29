<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\User;
use App\Support\ContentMasker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'Africa/Abidjan'));
    }

    private function publication(string $title, array $attrs = []): Publication
    {
        return Publication::create($attrs + ['type' => 'text', 'title' => $title]);
    }

    public function test_index_filters_pagination_and_visibility(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->publication("Article $i", ['published_at' => now()->subDays(20 - $i), 'category' => $i % 2 ? 'Liturgie' : 'Chantier']);
        }
        $this->publication('Vidéo à la une', ['type' => 'video', 'is_featured' => true, 'published_at' => now()->subDays(30)]);
        $this->publication('Album', ['type' => 'photo', 'published_at' => now()->subDay()]);
        $this->publication('Programmée', ['published_at' => now()->addDay()]);
        $this->publication('Brouillon', ['status' => 'draft']);

        $page = $this->getJson('/api/publications')->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 9)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(9, 'data');
        // Plus récentes d'abord
        $this->assertSame(['Album', 'Article 10', 'Article 9'], array_slice(array_column($page->json('data'), 'title'), 0, 3));

        $this->getJson('/api/publications?type=video')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Vidéo à la une');
        $this->getJson('/api/publications?type=photo')->assertJsonPath('data.0.title', 'Album');
        $this->getJson('/api/publications?featured=1')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.is_featured', true);
        $this->getJson('/api/publications?category=Chantier')->assertJsonPath('meta.total', 5);
        $this->getJson('/api/publications?exclude=album')->assertJsonPath('meta.total', 11);
        $this->getJson('/api/publications?per_page=100')->assertJsonPath('meta.per_page', 50);
        $this->getJson('/api/publications?type=autre')->assertUnprocessable();

        $this->getJson('/api/publications/programmee')->assertNotFound();
        $this->getJson('/api/publications/brouillon')->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/publications?all=1')->assertJsonPath('meta.total', 14);
    }

    public function test_show_by_slug_and_computed_fields(): void
    {
        $p = $this->publication('Retour en vidéo sur la messe', [
            'type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10',
            'video_duration' => '4:32', 'body' => trim(str_repeat('mot ', 450)),
            'gallery' => ['storage/publications/a.jpg', 'storage/publications/b.jpg'],
        ]);
        $p->comments()->create(['author' => 'A', 'content' => 'ok', 'status' => 'published']);
        $p->comments()->create(['author' => 'B', 'content' => 'en attente']);

        $this->getJson('/api/publications/retour-en-video-sur-la-messe')->assertOk()
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.youtube_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.comments_count', 1)
            ->assertJsonPath('data.photos_count', 2)
            ->assertJsonPath('data.reading_minutes', 2)
            ->assertJsonPath('data.author_label', 'Service communication de la paroisse')
            ->assertJsonPath('data.gallery.0', rtrim(env('APP_URL'), '/') . '/storage/publications/a.jpg')
            ->assertJsonPath('data.published_at', '2026-10-01 10:00:00');

        $this->getJson("/api/publications/{$p->id}")->assertOk()->assertJsonPath('data.slug', 'retour-en-video-sur-la-messe');

        $short = $this->publication('Court', ['video_url' => 'https://youtu.be/abcdefghijk']);
        $this->getJson("/api/publications/{$short->id}")->assertJsonPath('data.youtube_id', 'abcdefghijk')->assertJsonPath('data.reading_minutes', 1);
    }

    public function test_like_toggles_once_per_device(): void
    {
        $p = $this->publication('Article');

        $this->postJson("/api/publications/{$p->id}/like", ['device_id' => 'device-1'])
            ->assertOk()->assertJson(['data' => ['liked' => true, 'likes_count' => 1]]);
        $this->postJson("/api/publications/{$p->id}/like", ['device_id' => 'device-2'])
            ->assertJson(['data' => ['liked' => true, 'likes_count' => 2]]);
        $this->getJson("/api/publications/{$p->id}/like?device_id=device-1")
            ->assertJson(['data' => ['liked' => true, 'likes_count' => 2]]);

        // Deuxième appui du même appareil : retire le « j'aime »
        $this->postJson("/api/publications/{$p->id}/like", ['device_id' => 'device-1'])
            ->assertJson(['data' => ['liked' => false, 'likes_count' => 1]]);
        $this->getJson("/api/publications/{$p->id}/like?device_id=device-1")
            ->assertJson(['data' => ['liked' => false, 'likes_count' => 1]]);

        $this->assertSame(1, $p->fresh()->likes_count);
        // L'identifiant n'est pas stocké en clair
        $this->assertDatabaseMissing('publication_likes', ['device_hash' => 'device-2']);
        $this->postJson("/api/publications/{$p->id}/like", [])->assertUnprocessable();
    }

    public function test_comments_are_masked_moderated_and_likeable(): void
    {
        $p = $this->publication('Article');

        $this->postJson("/api/publications/{$p->id}/comments", [
            'author'  => 'Awa',
            'content' => 'Merci ! Voir https://spam.example/x ou www.promo.ci, exemple.com et appelez le +225 07 00 00 00 00 ou 0700000000 ou 07.00.00.00.00',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.initial', 'A')
            ->assertJsonPath('data.content', 'Merci ! Voir [masqué] ou [masqué], [masqué] et appelez le [masqué] ou [masqué] ou [masqué]');

        $this->postJson("/api/publications/{$p->id}/comments", ['author' => 'X', 'content' => str_repeat('a', 1001)])->assertUnprocessable();

        // En attente : non visible
        $this->getJson("/api/publications/{$p->id}/comments")->assertOk()->assertJsonCount(0, 'data');

        $comment = PublicationComment::first();

        $this->putJson("/api/comments/{$comment->id}", ['status' => 'published'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/comments?status=pending')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.publication.title', 'Article');

        $this->putJson("/api/comments/{$comment->id}", ['status' => 'published', 'reply' => 'Merci pour votre message.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.reply', 'Merci pour votre message.');
        $this->assertNotNull($comment->fresh()->replied_at);

        $this->travel(1)->minutes();
        $p->comments()->create(['author' => 'Jean', 'content' => 'Plus récent', 'status' => 'published']);
        $p->comments()->create(['author' => 'Rejeté', 'content' => 'x', 'status' => 'rejected']);

        $this->getJson("/api/publications/{$p->id}/comments")->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.author', 'Jean')
            ->assertJsonStructure(['data' => [['id', 'author', 'initial', 'content', 'reply', 'replied_at', 'likes_count', 'created_at', 'status']]]);
        $this->getJson("/api/publications/{$p->id}")->assertJsonPath('data.comments_count', 2);

        // J'aime sur un commentaire publié
        $this->postJson("/api/comments/{$comment->id}/like", ['device_id' => 'd1'])->assertJson(['data' => ['liked' => true, 'likes_count' => 1]]);
        $this->postJson("/api/comments/{$comment->id}/like", ['device_id' => 'd1'])->assertJson(['data' => ['liked' => false, 'likes_count' => 0]]);
        $rejected = PublicationComment::where('status', 'rejected')->first();
        $this->postJson("/api/comments/{$rejected->id}/like", ['device_id' => 'd1'])->assertNotFound();

        $this->deleteJson("/api/comments/{$comment->id}")->assertNoContent();
        $this->assertSoftDeleted('publication_comments', ['id' => $comment->id]);
    }

    public function test_masker_keeps_ordinary_text(): void
    {
        $text = 'Rendez-vous le 12/10 à 9 h, salle 3. M.Kouassi remercie les 150 fidèles.';
        $this->assertSame($text, ContentMasker::mask($text));
        $this->assertSame('Contact : [masqué]', ContentMasker::mask('Contact : 07 00 00 00 00'));
    }

    public function test_admin_crud_with_cover_and_gallery(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $id = $this->post('/api/publications', [
            'type' => 'photo', 'title' => 'La kermesse en images', 'format' => 'Album photo',
            'cover' => UploadedFile::fake()->create('c.jpg', 20, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.slug', 'la-kermesse-en-images')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_featured', false)
            ->json('data.id');

        $this->post("/api/publications/{$id}/gallery", ['image' => UploadedFile::fake()->create('g.jpg', 20, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.photos_count', 1);
        $this->deleteJson("/api/publications/{$id}/gallery/0")->assertOk()->assertJsonPath('data.photos_count', 0);
        $this->deleteJson("/api/publications/{$id}/gallery/3")->assertNotFound();

        $this->putJson("/api/publications/{$id}", ['is_featured' => true, 'type' => 'invalide'])->assertUnprocessable();
        $this->putJson("/api/publications/{$id}", ['is_featured' => true])->assertOk()->assertJsonPath('data.is_featured', true);

        $this->deleteJson("/api/publications/{$id}")->assertNoContent();
        $this->assertSoftDeleted('publications', ['id' => $id]);
    }
}
