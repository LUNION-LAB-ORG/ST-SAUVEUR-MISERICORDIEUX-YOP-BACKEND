<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    // Pas de created_at, juste updated_at
    public $timestamps = true;
    const CREATED_AT = null;

    protected $fillable = ['key', 'value', 'group', 'type', 'label'];

    protected static function booted(): void
    {
        // Invalider le cache quand un setting change
        static::saved(fn () => Cache::forget('settings:all'));
        static::deleted(fn () => Cache::forget('settings:all'));
    }

    /** Types dont la valeur n'est jamais exposée par l'API. */
    public const SECRET_TYPE = 'secret';

    /**
     * Récupère toutes les settings sous forme de map key → value (cache 5 min).
     * Les paramètres de type « secret » sont toujours renvoyés à null.
     */
    public static function allAsMap(): array
    {
        return Cache::remember('settings:all', 300, function () {
            return self::query()->get(['key', 'value', 'type'])
                ->mapWithKeys(fn (self $s) => [$s->key => $s->type === self::SECRET_TYPE ? null : $s->value])
                ->toArray();
        });
    }

    /** Valeur brute d'un paramètre (y compris secret) — usage serveur uniquement. */
    public static function rawValue(string $key): ?string
    {
        return self::query()->where('key', $key)->value('value');
    }

    public function isSecret(): bool
    {
        return $this->type === self::SECRET_TYPE;
    }

    /**
     * Rôles autorisés à modifier ce paramètre (l'admin passe partout).
     *
     * @return string[]
     */
    public static function editorRoles(string $key, ?string $type = null): array
    {
        if ($type === self::SECRET_TYPE || str_starts_with($key, 'payment.') || str_starts_with($key, 'whatsapp.')) {
            return ['admin'];
        }
        if (str_starts_with($key, 'pastor_word.')) {
            return ['admin', 'priest'];
        }
        if (str_starts_with($key, 'history.')) {
            return ['admin', 'communication', 'priest'];
        }

        return ['admin', 'secretariat', 'communication'];
    }

    /** Lecture simple avec fallback */
    public static function get(string $key, ?string $default = null): ?string
    {
        $map = self::allAsMap();
        return $map[$key] ?? $default;
    }
}
