<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery fee now depends on driving time and on how busy it was when
 * the order was placed. Both are kept with the delivery, beside the
 * distance, so any fee can be explained afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('order_deliveries', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->nullable()->after('distance_km');
            }
            if (! Schema::hasColumn('order_deliveries', 'fee_multiplier')) {
                $table->decimal('fee_multiplier', 4, 2)->default(1)->after('duration_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_deliveries', fn (Blueprint $table) => $table->dropColumn(['duration_minutes', 'fee_multiplier']));
    }
};
