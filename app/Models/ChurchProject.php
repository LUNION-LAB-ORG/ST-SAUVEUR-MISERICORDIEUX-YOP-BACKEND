<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Projet « Nouvelle église » (ligne unique).
 */
class ChurchProject extends Model
{
    public const PHASE_STATUSES = ['done', 'in_progress', 'upcoming'];

    protected $guarded = [];

    protected $casts = [
        'goal_amount'       => 'int',
        'adjustment_amount' => 'int',
        'phases'            => 'array',
        'gallery'           => 'array',
    ];

    /** Retourne la ligne unique (recréée avec les valeurs par défaut si absente). */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create([
                'title'             => 'Construction de la nouvelle église',
                'goal_amount'       => 0,
                'adjustment_amount' => 0,
                'phases'            => array_map(
                    fn ($name) => ['name' => $name, 'status' => 'upcoming'],
                    ['Études et permis', 'Fondations', 'Gros œuvre', 'Toiture', 'Finitions']
                ),
                'gallery'           => [],
            ]);
    }

    /** Somme des dons en ligne réussis rattachés au projet + ajustement hors ligne. */
    public function collectedAmount(): int
    {
        $label = Setting::get('donation.project_label') ?: 'Nouvelle église';

        $online = (int) Donation::query()
            ->where('payment_status', 'succeeded')
            ->where('project', $label)
            ->sum('amount');

        return $online + (int) $this->adjustment_amount;
    }

    /** Pourcentage d'avancement de la collecte (0 si objectif nul, plafonné à 100). */
    public function progress(?int $collected = null): int
    {
        $goal = (int) $this->goal_amount;
        if ($goal <= 0) {
            return 0;
        }

        $collected ??= $this->collectedAmount();

        return (int) max(0, min(100, floor($collected / $goal * 100)));
    }
}
