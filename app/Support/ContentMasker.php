<?php

namespace App\Support;

/**
 * Masque les liens et numéros de téléphone dans les contenus publics (commentaires).
 */
class ContentMasker
{
    public const MASK = '[masqué]';

    /** Extensions de domaine reconnues pour les liens écrits sans http ni www (« exemple.com »). */
    private const TLDS = 'com|net|org|info|biz|io|co|me|tv|app|dev|xyz|online|site|shop|store|link|click|live|top|'
        . 'africa|ci|fr|be|ch|ca|us|uk|de|sn|cm|bf|ml|tg|bj|ga|cd|ne|gn|mg|ma|tn|dz';

    public static function mask(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $patterns = [
            // http(s)://… et www.…
            '~\b(?:https?://|www\.)[^\s<>"]*[^\s<>".,;:!?)\]]~iu',
            // domaine nu : exemple.com, mon-site.ci/page
            '~\b[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9-]+)*\.(?:' . self::TLDS . ')\b(?:/[^\s<>"]*[^\s<>".,;:!?)\]])?~iu',
            // téléphones : au moins 8 chiffres, espaces / points / tirets autorisés, « + » initial facultatif
            '~(?<![\w])\+?\d(?:[\s.\-]{0,2}\d){7,}~u',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, self::MASK, $text);
        }

        // Fusionner les masques consécutifs
        $mask = preg_quote(self::MASK, '~');
        return preg_replace('~' . $mask . '(?:\s*' . $mask . ')+~u', self::MASK, $text);
    }
}
