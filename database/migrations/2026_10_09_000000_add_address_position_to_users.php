<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's saved address gets a map position, so checkout can open with
 * the address already filled in and the delivery fee already worked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'address_latitude')) {
                $table->decimal('address_latitude', 10, 7)->nullable()->after('address');
            }
            if (! Schema::hasColumn('users', 'address_longitude')) {
                $table->decimal('address_longitude', 10, 7)->nullable()->after('address_latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['address_latitude', 'address_longitude']));
    }
};
