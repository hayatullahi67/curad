<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'assigned_pharmacy_id')) {
                $table->unsignedBigInteger('assigned_pharmacy_id')->nullable()->after('created_by_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'assigned_pharmacy_id')) {
                $table->dropColumn('assigned_pharmacy_id');
            }

            if (Schema::hasColumn('users', 'created_by_user_id')) {
                $table->dropColumn('created_by_user_id');
            }
        });
    }
};
