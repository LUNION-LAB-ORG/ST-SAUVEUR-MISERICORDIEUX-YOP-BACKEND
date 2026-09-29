<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Slug unique généré depuis le titre à la création (suffixes -2, -3… en cas de collision).
 * Les lignes supprimées (soft delete) sont prises en compte : l'index unique les couvre.
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::creating(function ($model) {
            $model->slug = static::uniqueSlug($model->slug ?: $model->title);
        });
    }

    public static function uniqueSlug(?string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug((string) $source) ?: Str::lower(class_basename(static::class));
        $base = Str::limit($base, 180, '');
        $slug = $base;

        for ($i = 2; static::slugExists($slug, $ignoreId); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    protected static function slugExists(string $slug, ?int $ignoreId): bool
    {
        $query = static::query()->withoutGlobalScopes()->where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /** Recherche par id numérique ou par slug. */
    public static function findByIdOrSlug(string $idOrSlug): ?static
    {
        return ctype_digit($idOrSlug)
            ? static::query()->find((int) $idOrSlug)
            : static::query()->where('slug', $idOrSlug)->first();
    }
}
