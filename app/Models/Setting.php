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

    /** Récupère toutes les settings sous forme de map key → value (cache 5 min) */
    public static function allAsMap(): array
    {
        return Cache::remember('settings:all', 300, function () {
            return self::query()->pluck('value', 'key')->toArray();
        });
    }

    /** Lecture simple avec fallback */
    public static function get(string $key, ?string $default = null): ?string
    {
        $map = self::allAsMap();
        return $map[$key] ?? $default;
    }
}
