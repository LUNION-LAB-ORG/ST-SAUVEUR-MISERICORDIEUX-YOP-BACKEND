<?php

namespace App\Services;

use App\Models\LiturgyDay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Récupération et nettoyage de la liturgie du jour depuis l'API AELF.
 * Source : GET https://api.aelf.org/v1/messes/{YYYY-MM-DD}/{zone}
 */
class AelfService
{
    public const TIMEZONE = 'Africa/Abidjan';

    /** Balises HTML conservées dans les textes liturgiques (sans attributs). */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em'];

    /** Phrase du journal d'activité : « Textes AELF importés : 7 jours ». */
    public static function importDescription(int $days): string
    {
        return 'Textes AELF importés : ' . $days . ' ' . ($days > 1 ? 'jours' : 'jour');
    }

    /**
     * Importe (upsert) la journée liturgique d'une date.
     * En cas d'échec : log et aucune donnée existante n'est écrasée.
     */
    public function import(string $date): ?LiturgyDay
    {
        $payload = $this->fetch($date);
        if ($payload === null) {
            return null;
        }

        // Les surcharges admin (feast_override, color_override) ne sont pas touchées
        return LiturgyDay::updateOrCreate(
            ['date' => $date],
            $payload + ['imported_at' => now()]
        );
    }

    /**
     * Interroge l'API et renvoie les données normalisées, ou null en cas d'échec.
     */
    public function fetch(string $date): ?array
    {
        $zone = config('services.aelf.zone', 'afrique');
        $url  = rtrim(config('services.aelf.base_url', 'https://api.aelf.org/v1'), '/')
            . '/messes/' . $date . '/' . $zone;

        try {
            $response = Http::timeout(15)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            Log::error('AELF: erreur de connexion', ['date' => $date, 'message' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) {
            Log::error('AELF: réponse en erreur', ['date' => $date, 'status' => $response->status()]);
            return null;
        }

        $json = $response->json();
        $messes = $json['messes'] ?? null;
        if (!is_array($json) || !is_array($messes) || empty($messes)) {
            Log::error('AELF: réponse inattendue (aucune messe)', ['date' => $date]);
            return null;
        }

        return [
            'zone'     => $zone,
            'feast'    => self::cleanText($json['informations']['jour_liturgique_nom'] ?? null),
            'degree'   => self::cleanText($json['informations']['degre'] ?? null),
            'color'    => self::cleanText($json['informations']['couleur'] ?? null),
            'readings' => $this->mapReadings($this->pickMass($messes)),
        ];
    }

    /** « Messe du jour » si présente, sinon la première messe. */
    private function pickMass(array $messes): array
    {
        foreach ($messes as $messe) {
            if (mb_strtolower(trim((string) ($messe['nom'] ?? ''))) === 'messe du jour') {
                return $messe;
            }
        }

        return $messes[0] ?? [];
    }

    /** Lectures dans l'ordre de l'API (plusieurs lecture_1 possibles : « ou bien »). */
    private function mapReadings(array $messe): array
    {
        $readings = [];

        foreach ($messe['lectures'] ?? [] as $lecture) {
            if (!is_array($lecture)) {
                continue;
            }

            $readings[] = [
                'type'        => (string) ($lecture['type'] ?? 'autre'),
                'ref'         => self::cleanText($lecture['ref'] ?? null),
                'title'       => self::cleanText($lecture['titre'] ?? null),
                'intro'       => self::cleanText($lecture['intro_lue'] ?? null),
                'content'     => self::sanitizeHtml($lecture['contenu'] ?? null),
                'refrain'     => self::sanitizeHtml($lecture['refrain_psalmique'] ?? null),
                'refrain_ref' => self::cleanText($lecture['ref_refrain'] ?? null),
                'verse'       => self::sanitizeHtml($lecture['verset_evangile'] ?? null),
                'verse_ref'   => self::cleanText($lecture['ref_verset'] ?? null),
            ];
        }

        return $readings;
    }

    /**
     * Nettoyage HTML : liste blanche p, br, strong, em ; tout attribut est retiré.
     * Les espaces insécables sont remplacés par des espaces simples.
     */
    public static function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Supprimer entièrement le contenu des blocs script/style
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html);

        // Retirer toute balise hors liste blanche (le texte est conservé)
        $html = strip_tags($html, '<' . implode('><', self::ALLOWED_TAGS) . '>');

        // Retirer les attributs des balises conservées (valeurs entre guillemets gérées)
        $html = preg_replace_callback(
            '#<(/?)(' . implode('|', self::ALLOWED_TAGS) . ')\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>#i',
            function ($m) {
                $tag = strtolower($m[2]);
                if ($tag === 'br') {
                    return '<br />';
                }
                return '<' . $m[1] . $tag . '>';
            },
            $html
        );

        $html = self::normalizeSpaces($html);

        // Espaces en bord de paragraphe et paragraphes vides
        $html = preg_replace(['#<p>\s+#', '#\s+</p>#'], ['<p>', '</p>'], $html);
        $html = trim(preg_replace('#<p>(\s|<br />)*</p>\s*#', '', $html));

        return $html === '' ? null : $html;
    }

    /** Texte brut : sans balise, entités décodées, espaces normalisés. */
    public static function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = self::normalizeSpaces($text);

        return $text === '' ? null : $text;
    }

    /**
     * Espaces insécables (U+00A0, U+202F, &nbsp;) → espace simple,
     * puis réduction des espaces multiples et des espaces en bord de ligne.
     */
    private static function normalizeSpaces(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\u{202F}", '&nbsp;', '&#160;', '&#xa0;', '&#xA0;'], ' ', $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value);
        $value = preg_replace('/ *(\r?\n) */u', '$1', $value);

        return trim($value);
    }
}
