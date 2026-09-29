<?php

namespace Tests\Feature;

use App\Models\Homily;
use App\Models\LiturgyDay;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiturgyTest extends TestCase
{
    use RefreshDatabase;

    /** Réponse AELF réaliste : 2 lecture_1 (« ou bien »), psaume, évangile. */
    private function aelfPayload(string $date): array
    {
        return [
            'informations' => [
                'date'                => $date,
                'zone'                => 'romain',
                'couleur'             => 'blanc',
                'jour_liturgique_nom' => 'Saint Michel, Saint Gabriel et Saint Raphaël, Archanges',
                'fete'                => 'Fête',
                'degre'               => 'Fête',
            ],
            'messes' => [
                [
                    'nom' => 'Messe de la veille',
                    'lectures' => [
                        ['type' => 'evangile', 'ref' => 'Mt 1, 1', 'titre' => 'Mauvaise messe', 'contenu' => '<p>Ne pas prendre</p>'],
                    ],
                ],
                [
                    'nom' => 'Messe du jour',
                    'lectures' => [
                        [
                            'type'              => 'lecture_1',
                            'refrain_psalmique' => null,
                            'ref_refrain'       => null,
                            'titre'             => "«\u{00A0}Des millions d’êtres le servaient\u{00A0}»",
                            'contenu'           => "<p class=\"x\" style=\"color:red\">La nuit, au cours d’une vision,<br />\n\u{00A0}\u{00A0} \u{00A0}moi, Daniel, je regardais\u{00A0}:<br />\n<span data-v=\"1\">des trônes</span> furent disposés.</p><script>alert('x')</script><a href=\"http://evil\">lien</a>",
                            'ref'               => "Dn 7,\u{00A0}9-10.13-14",
                            'intro_lue'         => 'Lecture du livre du prophète Daniel',
                            'verset_evangile'   => null,
                            'ref_verset'        => null,
                        ],
                        [
                            'type'              => 'lecture_1',
                            'refrain_psalmique' => null,
                            'ref_refrain'       => null,
                            'titre'             => "«\u{00A0}Michel, avec ses anges, dut combattre le Dragon\u{00A0}»",
                            'contenu'           => '<p>Il y eut un combat dans le ciel&nbsp;:<br />Michel, avec ses anges.</p>',
                            'ref'               => 'Ap 12, 7-12a',
                            'intro_lue'         => "Lecture de l'Apocalypse de saint Jean",
                            'verset_evangile'   => null,
                            'ref_verset'        => null,
                        ],
                        [
                            'type'              => 'psaume',
                            'refrain_psalmique' => '<p><strong>Je te chante, Seigneur, en présence des anges.</strong></p>',
                            'ref_refrain'       => 'cf. 137, 1c',
                            'titre'             => null,
                            'contenu'           => '<p>De tout mon cœur, Seigneur, je te rends grâce&nbsp;:<br />tu as entendu les paroles de ma bouche.</p>',
                            'ref'               => "Ps 137 (138),\u{00A0}1-2a",
                            'intro_lue'         => null,
                            'verset_evangile'   => null,
                            'ref_verset'        => null,
                        ],
                        [
                            'type'              => 'evangile',
                            'refrain_psalmique' => null,
                            'ref_refrain'       => null,
                            'titre'             => "«\u{00A0}Vous verrez les anges de Dieu\u{00A0}»",
                            'contenu'           => '<p>En ce temps-là, <em>lorsque Jésus vit Nathanaël</em>…</p>',
                            'ref'               => 'Jn 1, 47-51',
                            'intro_lue'         => 'Évangile de Jésus Christ selon saint Jean',
                            'verset_evangile'   => '<p><strong>Alléluia. Alléluia. </strong><br />Tous les anges du Seigneur, bénissez le Seigneur</p>',
                            'ref_verset'        => 'Dn 3, 58',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function fakeAelfOk(): void
    {
        Http::fake(function ($request) {
            preg_match('#/messes/(\d{4}-\d{2}-\d{2})/afrique$#', $request->url(), $m);
            return Http::response($this->aelfPayload($m[1] ?? '2026-09-29'), 200);
        });
    }

    public function test_import_command_stores_cleaned_readings(): void
    {
        $this->fakeAelfOk();

        $this->artisan('liturgy:import', ['--date' => '2026-09-29', '--days' => 2])->assertExitCode(0);

        $this->assertSame(2, LiturgyDay::count());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/messes/2026-09-29/afrique'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/messes/2026-09-30/afrique'));

        $day = LiturgyDay::where('date', '2026-09-29')->first();
        $this->assertSame('Saint Michel, Saint Gabriel et Saint Raphaël, Archanges', $day->feast);
        $this->assertSame('Fête', $day->degree);
        $this->assertSame('blanc', $day->color);
        $this->assertSame('afrique', $day->zone);

        $readings = $day->readings;
        $this->assertSame(['lecture_1', 'lecture_1', 'psaume', 'evangile'], array_column($readings, 'type'));

        $first = $readings[0];
        // Nettoyage : attributs supprimés, balises hors liste blanche retirées, script supprimé
        $this->assertStringNotContainsString('class=', $first['content']);
        $this->assertStringNotContainsString('style=', $first['content']);
        $this->assertStringNotContainsString('<span', $first['content']);
        $this->assertStringNotContainsString('<a', $first['content']);
        $this->assertStringNotContainsString('<script', $first['content']);
        $this->assertStringNotContainsString('alert', $first['content']);
        $this->assertStringContainsString('<p>La nuit', $first['content']);
        $this->assertStringContainsString('<br />', $first['content']);
        $this->assertStringContainsString('des trônes furent disposés', $first['content']);
        $this->assertStringContainsString('lien', $first['content']);
        // Espaces insécables normalisés, texte conservé
        $this->assertStringNotContainsString("\u{00A0}", $first['content']);
        $this->assertStringContainsString('moi, Daniel, je regardais :', $first['content']);
        $this->assertSame('« Des millions d’êtres le servaient »', $first['title']);
        $this->assertSame('Dn 7, 9-10.13-14', $first['ref']);
        $this->assertSame('Lecture du livre du prophète Daniel', $first['intro']);

        $this->assertStringNotContainsString('&nbsp;', $readings[1]['content']);
        $this->assertSame('<p><strong>Je te chante, Seigneur, en présence des anges.</strong></p>', $readings[2]['refrain']);
        $this->assertSame('cf. 137, 1c', $readings[2]['refrain_ref']);
        $this->assertStringContainsString('<em>lorsque Jésus vit Nathanaël</em>', $readings[3]['content']);
        $this->assertSame('Dn 3, 58', $readings[3]['verse_ref']);
        $this->assertStringStartsWith('<p><strong>Alléluia.', $readings[3]['verse']);
    }

    public function test_get_liturgy_imports_on_the_fly_and_returns_contract_shape(): void
    {
        $this->fakeAelfOk();

        $priest = Priest::create(['fullname' => 'Père Jean', 'function' => 'Curé']);
        Homily::create([
            'date' => '2026-09-29', 'priest_id' => $priest->id, 'title' => 'Les anges',
            'content' => 'Texte', 'status' => 'published',
        ]);
        Homily::create(['date' => '2026-09-29', 'title' => 'Brouillon', 'content' => 'x', 'status' => 'draft']);

        $response = $this->getJson('/api/liturgy?date=2026-09-29');

        $response->assertOk()
            ->assertJsonPath('data.date', '2026-09-29')
            ->assertJsonPath('data.feast', 'Saint Michel, Saint Gabriel et Saint Raphaël, Archanges')
            ->assertJsonPath('data.degree', 'Fête')
            ->assertJsonPath('data.color', 'blanc')
            ->assertJsonPath('data.is_fallback', false)
            ->assertJsonPath('data.readings.0.type', 'lecture_1')
            ->assertJsonPath('data.readings.0.refrain', null)
            ->assertJsonPath('data.homily.title', 'Les anges')
            ->assertJsonPath('data.homily.author.fullname', 'Père Jean')
            ->assertJsonPath('data.homily.author.function', 'Curé')
            ->assertJsonStructure(['data' => [
                'date', 'feast', 'degree', 'color', 'is_fallback', 'imported_at',
                'readings' => [['type', 'ref', 'title', 'intro', 'content', 'refrain', 'refrain_ref', 'verse', 'verse_ref']],
                'homily' => ['id', 'title', 'content', 'audio_url', 'author' => ['fullname', 'function']],
            ]]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $response->json('data.imported_at'));
        $this->assertSame(1, LiturgyDay::count());
    }

    public function test_admin_override_is_returned_and_survives_reimport(): void
    {
        $this->fakeAelfOk();
        $this->artisan('liturgy:import', ['--date' => '2026-09-29', '--days' => 1])->assertExitCode(0);

        $this->putJson('/api/liturgy/2026-09-29', ['feast_override' => 'Fête patronale'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/liturgy/2026-09-29', ['feast_override' => 'Fête patronale', 'color_override' => 'rouge'])
            ->assertOk()
            ->assertJsonPath('data.feast', 'Fête patronale')
            ->assertJsonPath('data.color', 'rouge');

        // Un nouvel import ne doit pas écraser la surcharge
        $this->artisan('liturgy:import', ['--date' => '2026-09-29', '--days' => 1])->assertExitCode(0);

        $this->getJson('/api/liturgy?date=2026-09-29')
            ->assertOk()
            ->assertJsonPath('data.feast', 'Fête patronale')
            ->assertJsonPath('data.color', 'rouge')
            ->assertJsonPath('data.degree', 'Fête');

        $this->getJson('/api/liturgy/days?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_import_endpoint_reports_imported_and_failed(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '2026-09-30')) {
                return Http::response('boom', 500);
            }
            preg_match('#/messes/(\d{4}-\d{2}-\d{2})/#', $request->url(), $m);
            return Http::response($this->aelfPayload($m[1]), 200);
        });

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/liturgy/import', ['date' => '2026-09-29', 'days' => 3])
            ->assertOk()
            ->assertJsonPath('data.imported', ['2026-09-29', '2026-10-01'])
            ->assertJsonPath('data.failed', ['2026-09-30']);
    }

    public function test_failure_does_not_overwrite_and_falls_back_to_last_day(): void
    {
        $aelfUp = true;
        Http::fake(function ($request) use (&$aelfUp) {
            return $aelfUp
                ? Http::response($this->aelfPayload('2026-09-27'), 200)
                : Http::response(['error' => 'down'], 503);
        });

        $this->artisan('liturgy:import', ['--date' => '2026-09-27', '--days' => 1])->assertExitCode(0);
        $before = LiturgyDay::where('date', '2026-09-27')->first()->readings;

        $aelfUp = false;

        // L'échec d'import ne touche pas aux données existantes
        $this->artisan('liturgy:import', ['--date' => '2026-09-27', '--days' => 1])->assertExitCode(1);
        $this->assertEquals($before, LiturgyDay::where('date', '2026-09-27')->first()->readings);

        // Date absente + AELF en échec → dernière journée ≤ date avec is_fallback
        $this->getJson('/api/liturgy?date=2026-09-29')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-09-27')
            ->assertJsonPath('data.is_fallback', true);
    }

    public function test_returns_404_when_nothing_available(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->getJson('/api/liturgy?date=2026-09-29')->assertNotFound();
    }

    public function test_sanitizer_whitelist(): void
    {
        $html = '<div><P ALIGN="center" onclick="x()">Texte <b>gras</b> <strong class="a">fort</strong> <em title="a>b">it</em><br class="c"/><img src="x"></P></div><style>p{}</style>'
            . "<p>&nbsp;</p>\n<p> Suite&nbsp;!</p>";

        $this->assertSame(
            "<p>Texte gras <strong>fort</strong> <em>it</em><br /></p><p>Suite !</p>",
            \App\Services\AelfService::sanitizeHtml($html)
        );
    }
}
