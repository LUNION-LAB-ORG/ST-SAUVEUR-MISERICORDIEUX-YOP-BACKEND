<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Champs du back-office sur les contenus existants :
 *  - homilies : publication programmée, notification WhatsApp ;
 *  - events : ouverture des inscriptions ;
 *  - publications : commentaires et « j'aime » activables ;
 *  - listens : prêtre assigné, date proposée ;
 *  - whatsapp_subscribers : origine de l'abonnement.
 */
return new class extends Migration {
    private function columns(): array
    {
        return [
            'homilies' => [
                'publish_at'      => fn (Blueprint $t) => $t->dateTime('publish_at')->nullable(),
                'notify_whatsapp' => fn (Blueprint $t) => $t->boolean('notify_whatsapp')->default(false),
            ],
            'events' => [
                'registrations_open' => fn (Blueprint $t) => $t->boolean('registrations_open')->default(true),
            ],
            'publications' => [
                'allow_comments' => fn (Blueprint $t) => $t->boolean('allow_comments')->default(true),
                'show_likes'     => fn (Blueprint $t) => $t->boolean('show_likes')->default(true),
            ],
            'listens' => [
                'assigned_priest_id' => fn (Blueprint $t) => $t->foreignId('assigned_priest_id')->nullable()->constrained('priests')->nullOnDelete(),
                'proposed_at'        => fn (Blueprint $t) => $t->dateTime('proposed_at')->nullable(),
            ],
            'whatsapp_subscribers' => [
                'source' => fn (Blueprint $t) => $t->string('source', 50)->nullable(),
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column => $define) {
                    if (!Schema::hasColumn($table, $column)) {
                        $define($blueprint);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('listens', 'assigned_priest_id') && DB::getDriverName() !== 'sqlite') {
            Schema::table('listens', fn (Blueprint $t) => $t->dropForeign(['assigned_priest_id']));
        }

        foreach ($this->columns() as $table => $columns) {
            $existing = array_values(array_filter(array_keys($columns), fn ($c) => Schema::hasColumn($table, $c)));
            if ($existing) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing));
            }
        }
    }
};
