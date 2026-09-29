<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les « mouvements et groupes » sont gérés dans `services` :
 * ajout des champs de la refonte (catégorie, public, lieu, WhatsApp) et de la publication.
 * Les lignes existantes restent publiées (défaut « published »).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'category')) {
                // Texte libre (Liturgie, Prière, Jeunesse, Charité, Familles…)
                $table->string('category')->nullable()->index();
            }
            if (!Schema::hasColumn('services', 'audience')) {
                $table->string('audience')->nullable();
            }
            if (!Schema::hasColumn('services', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('services', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable();
            }
            if (!Schema::hasColumn('services', 'status')) {
                // draft | published | hidden
                $table->string('status', 20)->default('published')->index();
            }
            if (!Schema::hasColumn('services', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
        });
    }

    public function down(): void
    {
        // Index d'abord, puis un seul dropColumn (contrainte SQLite)
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'category')) $table->dropIndex(['category']);
            if (Schema::hasColumn('services', 'status')) $table->dropIndex(['status']);
        });

        $columns = array_values(array_filter(
            ['category', 'audience', 'location', 'whatsapp', 'status', 'sort_order'],
            fn ($column) => Schema::hasColumn('services', $column)
        ));

        if ($columns) {
            Schema::table('services', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
