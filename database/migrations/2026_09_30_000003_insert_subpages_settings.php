<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres des sous-pages (annonces, secrétariat, demande de messe).
 * insertOrIgnore : ne jamais écraser une valeur déjà saisie.
 */
return new class extends Migration {
    private function rows(): array
    {
        return [
            ['key' => 'announcements.sheet_pdf', 'group' => 'announcements', 'type' => 'file', 'label' => "Feuille d'annonces de la semaine (PDF)", 'value' => ''],
            ['key' => 'parish.office_hours', 'group' => 'parish', 'type' => 'textarea', 'label' => 'Horaires du secrétariat', 'value' => ''],
            ['key' => 'mass.offering_amount', 'group' => 'mass', 'type' => 'text', 'label' => 'Offrande indicative par messe (FCFA)', 'value' => ''],
            ['key' => 'mass.min_offering', 'group' => 'mass', 'type' => 'text', 'label' => 'Offrande minimale (FCFA)', 'value' => '0'],
            ['key' => 'mass.min_delay_hours', 'group' => 'mass', 'type' => 'text', 'label' => 'Délai minimal avant la messe (heures)', 'value' => '24'],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        DB::table('settings')->insertOrIgnore(array_map(fn ($row) => $row + ['updated_at' => $now], $this->rows()));
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', array_column($this->rows(), 'key'))->delete();
        }
    }
};
