<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pastors', function (Blueprint $table) {
            if (Schema::hasColumn('pastors', 'image') && !Schema::hasColumn('pastors', 'photo')) {
                $table->renameColumn('image', 'photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pastors', function (Blueprint $table) {
            if (Schema::hasColumn('pastors', 'photo') && !Schema::hasColumn('pastors', 'image')) {
                $table->renameColumn('photo', 'image');
            }
        });
    }
};
