<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            // When the product was put up for sale in Inventory. This is not
            // created_at: a product can sit in stock for a while before it is
            // listed, and Inventory sorts and filters by the listing date.
            if (! Schema::hasColumn('medications', 'listed_at')) $table->timestamp('listed_at')->nullable()->index();
        });

        // Products already for sale were listed the moment they were created.
        DB::table('medications')->where('is_listed', true)->whereNull('listed_at')->update(['listed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('medications', 'listed_at')) {
            Schema::table('medications', fn (Blueprint $table) => $table->dropColumn('listed_at'));
        }
    }
};
