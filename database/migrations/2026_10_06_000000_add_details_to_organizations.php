<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The licence number and the business contact details belong to the
 * organization, not to one of its branches. They start as a copy of what
 * the main branch already holds, so nothing has to be typed again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'license_number')) {
                $table->string('license_number')->nullable()->after('name');
            }
            if (! Schema::hasColumn('organizations', 'phone')) {
                $table->string('phone', 30)->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('organizations', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });

        $mainBranches = DB::table('pharmacies')->whereNotNull('organization_id')->orderByDesc('is_main')->orderBy('id')->get()->unique('organization_id');

        foreach ($mainBranches as $branch) {
            DB::table('organizations')->where('id', $branch->organization_id)->whereNull('license_number')->whereNull('phone')->whereNull('email')->update([
                'license_number' => $branch->license_number,
                'phone' => $branch->phone,
                'email' => $branch->email,
            ]);
        }

        // CuraD is for pharmacies only.
        DB::table('pharmacies')->where('type', '!=', 'pharmacy')->update(['type' => 'pharmacy']);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['license_number', 'phone', 'email']);
        });
    }
};
