<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification, the list of extra services and the logo were never part of
 * what CuraD does, and nothing reads them any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unused = array_values(array_filter(
            ['is_verified', 'verification_status', 'services', 'logo'],
            fn (string $column) => Schema::hasColumn('pharmacies', $column),
        ));

        if ($unused) {
            Schema::table('pharmacies', fn (Blueprint $table) => $table->dropColumn($unused));
        }
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false);
            $table->string('verification_status', 20)->default('unverified');
            $table->json('services')->nullable();
            $table->string('logo')->nullable();
        });
    }
};
