<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** CuraD does not collect licence numbers: they are not part of what the client asked for. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pharmacies', 'license_number')) {
            // The column carries a unique index, which has to go first.
            foreach (DB::select("SHOW INDEX FROM pharmacies WHERE Column_name = 'license_number'") as $index) {
                DB::statement("ALTER TABLE pharmacies DROP INDEX `{$index->Key_name}`");
            }

            Schema::table('pharmacies', fn (Blueprint $table) => $table->dropColumn('license_number'));
        }

        if (Schema::hasColumn('organizations', 'license_number')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('license_number'));
        }
    }

    public function down(): void
    {
        Schema::table('pharmacies', fn (Blueprint $table) => $table->string('license_number')->nullable()->unique());
        Schema::table('organizations', fn (Blueprint $table) => $table->string('license_number')->nullable());
    }
};
