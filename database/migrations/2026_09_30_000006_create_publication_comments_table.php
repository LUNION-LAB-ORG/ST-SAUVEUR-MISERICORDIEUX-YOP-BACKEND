<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commentaires des publications (modérés : pending → published | rejected).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('publication_comments')) {
            return;
        }

        Schema::create('publication_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->string('author', 80);
            $table->text('content');
            $table->string('status', 20)->default('pending')->index();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_comments');
    }
};
