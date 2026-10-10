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
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('role');
        });

        Schema::table('medications', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('category');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('patient_charge', 10, 2)->default(10.00)->after('status');
            $table->decimal('vendor_commission', 10, 2)->default(0.00)->after('patient_charge');
            $table->boolean('is_paid')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });

        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn('photo');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['patient_charge', 'vendor_commission', 'is_paid']);
        });
    }
};
