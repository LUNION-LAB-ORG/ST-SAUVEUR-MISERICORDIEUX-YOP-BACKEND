<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes nécessaires pour tracer les dons publics :
 *  - payment_status : 'pending' | 'succeeded' | 'failed' (default succeeded pour
 *    compat ascendante des dons créés manuellement par l'admin au dashboard).
 *  - email / phone  : coordonnées du donateur pour les dons publics (Wave ou
 *    paroisse) — optionnels pour les dons créés depuis le dashboard.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (!Schema::hasColumn('donations', 'payment_status')) {
                $table->string('payment_status', 20)->default('succeeded')->after('paytransaction');
            }
            if (!Schema::hasColumn('donations', 'email')) {
                $table->string('email')->nullable()->after('donator');
            }
            if (!Schema::hasColumn('donations', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (Schema::hasColumn('donations', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('donations', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('donations', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
