<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            if (!Schema::hasColumn('organisations', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('description');
            }
            if (!Schema::hasColumn('organisations', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('is_paid');
            }
            if (!Schema::hasColumn('organisations', 'max_participants')) {
                $table->integer('max_participants')->nullable()->after('price');
            }
            if (!Schema::hasColumn('organisations', 'registration_deadline')) {
                $table->dateTime('registration_deadline')->nullable()->after('max_participants');
            }
            if (!Schema::hasColumn('organisations', 'converted_event_id')) {
                $table->unsignedBigInteger('converted_event_id')->nullable()->after('request_status');
                $table->index('converted_event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropIndex(['converted_event_id']);
            $table->dropColumn([
                'is_paid',
                'price',
                'max_participants',
                'registration_deadline',
                'converted_event_id',
            ]);
        });
    }
};
