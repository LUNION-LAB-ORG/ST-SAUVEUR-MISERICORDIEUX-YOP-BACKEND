<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // Catégorie fonctionnelle : messe, listen, donation, event_register, organisation, system
            $table->string('type', 32)->index();
            // Icône associée (optionnel) : heart, flame, volume2, calendar, bell, settings
            $table->string('icon', 32)->nullable();
            $table->string('title', 200);
            $table->text('message')->nullable();
            // Lien vers ressource : type + id (polymorphe léger, pas d'enforcement FK)
            $table->string('related_type', 64)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            // URL interne dashboard où pointe la notification (ex: /dashboard/messes)
            $table->string('link', 255)->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_read', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
