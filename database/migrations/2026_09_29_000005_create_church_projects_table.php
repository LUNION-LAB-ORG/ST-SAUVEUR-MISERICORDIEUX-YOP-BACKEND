<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projet « Nouvelle église » : une seule ligne, créée ici avec des valeurs par défaut.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('church_projects')) {
            Schema::create('church_projects', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->longText('presentation')->nullable();
                $table->unsignedBigInteger('goal_amount')->default(0);
                // Dons hors ligne ajoutés manuellement au montant collecté
                $table->bigInteger('adjustment_amount')->default(0);
                // [{ "name": "...", "status": "done|in_progress|upcoming" }]
                $table->json('phases')->nullable();
                // Liste de chemins d'images
                $table->json('gallery')->nullable();
                // Vue d'architecte
                $table->string('image')->nullable();
                $table->timestamps();
            });
        }

        if (!DB::table('church_projects')->exists()) {
            $phases = array_map(
                fn ($name) => ['name' => $name, 'status' => 'upcoming'],
                ['Études et permis', 'Fondations', 'Gros œuvre', 'Toiture', 'Finitions']
            );

            DB::table('church_projects')->insert([
                'title'             => 'Construction de la nouvelle église',
                'presentation'      => null,
                'goal_amount'       => 0,
                'adjustment_amount' => 0,
                'phases'            => json_encode($phases, JSON_UNESCAPED_UNICODE),
                'gallery'           => json_encode([]),
                'image'             => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('church_projects');
    }
};
