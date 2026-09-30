<?php

namespace App\Console\Commands;

use App\Services\AelfService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Importe la liturgie du jour depuis l'API AELF (upsert par date).
 * Planifiée chaque jour à 00:05 (Africa/Abidjan) dans App\Console\Kernel.
 */
class ImportLiturgy extends Command
{
    protected $signature = 'liturgy:import
        {--date= : Date de départ (YYYY-MM-DD), défaut = aujourd\'hui (Africa/Abidjan)}
        {--days=7 : Nombre de jours à importer à partir de la date de départ}';

    protected $description = 'Importe les lectures de la messe depuis l\'API AELF';

    public function handle(AelfService $aelf): int
    {
        try {
            $start = $this->option('date')
                ? Carbon::createFromFormat('!Y-m-d', $this->option('date'), AelfService::TIMEZONE)
                : Carbon::now(AelfService::TIMEZONE)->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Date invalide, format attendu : YYYY-MM-DD');
            return self::INVALID;
        }

        $days = max(1, min(60, (int) $this->option('days')));

        $imported = [];
        $failed   = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();

            if ($aelf->import($date)) {
                $imported[] = $date;
                $this->line("✔ {$date}");
            } else {
                $failed[] = $date;
                $this->warn("✘ {$date} (échec, données existantes conservées)");
            }
        }

        $this->info(count($imported) . ' journée(s) importée(s), ' . count($failed) . ' échec(s).');

        if ($imported) {
            \App\Services\ActivityLogger::log('imported', null, \App\Services\AelfService::importDescription(count($imported)));
        }

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }
}
