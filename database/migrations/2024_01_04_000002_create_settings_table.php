<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // key est l'identifiant unique (ex: "parish.name", "social.facebook")
            $table->string('key', 100)->primary();
            // Valeur texte (suffisant pour URL, texte court/long)
            $table->text('value')->nullable();
            // Groupement (parish, social, images, hours, payment)
            $table->string('group', 50)->index();
            // Type de donnée pour le rendu côté frontend : text, textarea, url, email, phone, image, boolean
            $table->string('type', 20)->default('text');
            $table->string('label', 200)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
