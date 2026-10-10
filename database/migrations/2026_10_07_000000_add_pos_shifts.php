<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A seller's shift at the till: the cash they started with (float),
        // and at the end the cash they counted against what the system expected.
        if (! Schema::hasTable('pos_shifts')) {
            Schema::create('pos_shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id');
                $table->unsignedBigInteger('user_id');
                $table->string('status', 20)->default('open'); // open, closed
                $table->timestamp('opened_at')->nullable();
                $table->decimal('opening_float', 12, 2)->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable(); // a manager can close a shift left open
                $table->decimal('expected_cash', 12, 2)->nullable();
                $table->decimal('counted_cash', 12, 2)->nullable();
                $table->decimal('difference', 12, 2)->nullable(); // counted - expected: below 0 is short
                $table->json('cash_breakdown')->nullable(); // {"1000": 12, "500": 3, "other": 150}
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->index(['pharmacy_id', 'user_id', 'status']);
            });
        }

        Schema::table('pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sales', 'shift_id')) $table->unsignedBigInteger('shift_id')->nullable()->index()->after('staff_id');
        });

        // A cash refund comes out of the drawer of whoever handed it over.
        Schema::table('pos_refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_refunds', 'shift_id')) $table->unsignedBigInteger('shift_id')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');

        foreach (['pos_sales', 'pos_refunds'] as $tableName) {
            if (Schema::hasColumn($tableName, 'shift_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('shift_id'));
            }
        }
    }
};
