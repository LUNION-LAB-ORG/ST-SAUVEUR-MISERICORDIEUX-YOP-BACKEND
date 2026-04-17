<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (!Schema::hasColumn('donations', 'donation_type')) {
                // monetaire = argent ; nature = don en nature (biens, services)
                $table->string('donation_type', 20)->default('monetaire')->after('donator');
            }
        });

        // Make paytransaction nullable (not required for in-kind donations)
        Schema::table('donations', function (Blueprint $table) {
            $table->string('paytransaction', 50)->nullable()->change();
            $table->string('paymethod', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (Schema::hasColumn('donations', 'donation_type')) {
                $table->dropColumn('donation_type');
            }
            $table->string('paytransaction', 50)->nullable(false)->change();
            $table->string('paymethod', 50)->nullable(false)->change();
        });
    }
};
