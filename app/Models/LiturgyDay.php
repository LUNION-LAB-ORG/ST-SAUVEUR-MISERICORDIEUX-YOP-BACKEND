<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Journée liturgique importée depuis l'API AELF.
 *
 * @property int $id
 * @property string $date
 * @property string|null $zone
 * @property string|null $feast
 * @property string|null $degree
 * @property string|null $color
 * @property array|null $readings
 * @property string|null $feast_override
 * @property string|null $color_override
 * @property \Carbon\Carbon|null $imported_at
 */
class LiturgyDay extends Model
{
    protected $guarded = [];

    // La colonne `date` reste une chaîne Y-m-d (pas de cast) pour des comparaisons fiables.
    protected $casts = [
        'readings'    => 'array',
        'imported_at' => 'datetime',
    ];

    /** Fête affichée : la surcharge admin l'emporte. */
    public function displayFeast(): ?string
    {
        return $this->feast_override ?: $this->feast;
    }

    /** Couleur affichée : la surcharge admin l'emporte. */
    public function displayColor(): ?string
    {
        return $this->color_override ?: $this->color;
    }
}
