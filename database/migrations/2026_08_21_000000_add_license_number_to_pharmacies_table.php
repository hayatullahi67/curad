<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * license_number was previously only ever added via a runtime ALTER TABLE in
 * routes/web.php (vendor.onboarding) — formalized here so `migrate` is the
 * single source of truth for this column instead of an ad-hoc guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            if (! Schema::hasColumn('pharmacies', 'license_number')) {
                $table->string('license_number')->nullable()->unique()->after('address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            if (Schema::hasColumn('pharmacies', 'license_number')) {
                $table->dropColumn('license_number');
            }
        });
    }
};
