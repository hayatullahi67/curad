<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            // A product enters through the Stock page with its stock facts
            // (cost, batch, expiry, quantity) and is "listed" in Inventory
            // once it has a selling price and its selling details. It is one
            // record throughout, so POS, stock and marketplace always read
            // the same quantity. Existing products are already for sale.
            if (! Schema::hasColumn('medications', 'is_listed')) $table->boolean('is_listed')->default(true)->index();

            // Short-lived predecessor of is_listed on development databases.
            if (Schema::hasColumn('medications', 'needs_details')) $table->dropColumn('needs_details');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('medications', 'is_listed')) {
            Schema::table('medications', fn (Blueprint $table) => $table->dropColumn('is_listed'));
        }
    }
};
