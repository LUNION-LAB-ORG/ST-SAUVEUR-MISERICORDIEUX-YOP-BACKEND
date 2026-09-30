<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identifiant texte de l'objet journalisé (ex. clé de paramètre « parish.phone »),
 * pour les modèles dont la clé primaire n'est pas numérique.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('activity_logs') && !Schema::hasColumn('activity_logs', 'subject_key')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->string('subject_key', 100)->nullable()->after('subject_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'subject_key')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropColumn('subject_key');
            });
        }
    }
};
