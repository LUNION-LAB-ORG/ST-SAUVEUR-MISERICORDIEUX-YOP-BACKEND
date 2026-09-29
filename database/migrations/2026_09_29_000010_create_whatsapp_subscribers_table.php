<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnés aux diffusions WhatsApp (Parole du jour, annonces).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_subscribers')) {
            return;
        }

        Schema::create('whatsapp_subscribers', function (Blueprint $table) {
            $table->id();
            // Normalisé : chiffres et « + » uniquement
            $table->string('phone', 30)->unique();
            $table->json('lists')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_subscribers');
    }
};
