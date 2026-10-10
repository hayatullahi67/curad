<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messages used to hang off a reservation. Reservations were replaced by
 * orders, so a thread now belongs to a customer and a pharmacy, and
 * remembers which order it was last opened from.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE conversations MODIFY reservation_id BIGINT UNSIGNED NULL');

        if (! Schema::hasColumn('conversations', 'order_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->after('reservation_id');
                $table->index(['user_id', 'pharmacy_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'pharmacy_id']);
            $table->dropColumn('order_id');
        });
    }
};
