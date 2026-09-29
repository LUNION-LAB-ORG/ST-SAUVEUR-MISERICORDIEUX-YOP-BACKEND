<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Agenda : slug, chapeau, catégorie, public, heure de fin, programme et statut de publication.
 * Les événements existants reçoivent un slug unique et restent publiés.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'slug')) {
                $table->string('slug')->nullable()->after('title');
            }
            if (!Schema::hasColumn('events', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (!Schema::hasColumn('events', 'category')) {
                $table->string('category', 100)->nullable();
            }
            if (!Schema::hasColumn('events', 'audience')) {
                $table->string('audience')->nullable();
            }
            if (!Schema::hasColumn('events', 'end_time')) {
                $table->time('end_time')->nullable();
            }
            if (!Schema::hasColumn('events', 'programme')) {
                // [{ "time": "09:00", "label": "…" }] — null traité comme []
                $table->json('programme')->nullable();
            }
            if (!Schema::hasColumn('events', 'status')) {
                $table->string('status', 20)->default('published')->index();
            }
        });

        // Backfill des slugs (soft-deleted compris, l'index unique les couvre)
        $used = DB::table('events')->whereNotNull('slug')->pluck('slug')->all();
        DB::table('events')->whereNull('slug')->orderBy('id')->get(['id', 'title'])
            ->each(function ($event) use (&$used) {
                $base = Str::slug((string) $event->title) ?: 'evenement';
                $slug = $base;
                for ($i = 2; in_array($slug, $used, true); $i++) {
                    $slug = $base . '-' . $i;
                }
                $used[] = $slug;
                DB::table('events')->where('id', $event->id)->update(['slug' => $slug]);
            });

        DB::table('events')->whereNull('programme')->update(['programme' => '[]']);

        if (!Schema::hasIndex('events', 'events_slug_unique')) {
            Schema::table('events', fn (Blueprint $table) => $table->unique('slug'));
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasIndex('events', 'events_slug_unique')) $table->dropUnique(['slug']);
            if (Schema::hasIndex('events', 'events_status_index')) $table->dropIndex(['status']);
        });

        $columns = array_values(array_filter(
            ['slug', 'summary', 'category', 'audience', 'end_time', 'programme', 'status'],
            fn ($column) => Schema::hasColumn('events', $column)
        ));
        if ($columns) {
            Schema::table('events', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
