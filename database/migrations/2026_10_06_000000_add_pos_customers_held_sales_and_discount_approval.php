<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pharmacy's own customer list. The phone number is what
        // identifies a returning customer at the till.
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id');
                $table->string('name')->nullable();
                $table->string('phone', 30);
                $table->timestamps();

                $table->unique(['pharmacy_id', 'phone']);
            });
        }

        // Sales parked at the till ("Save for later"). Kept on the server so
        // a held sale survives a refresh and can be resumed on another till.
        if (! Schema::hasTable('pos_held_sales')) {
            Schema::create('pos_held_sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 30)->nullable();
                $table->json('items'); // [{id, name, price, quantity}]
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        Schema::table('pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sales', 'customer_id')) $table->unsignedBigInteger('customer_id')->nullable()->index()->after('staff_id');
            if (! Schema::hasColumn('pos_sales', 'discount_reason')) $table->string('discount_reason')->nullable()->after('discount_amount');
            // Set when the discount was above the seller's own limit.
            if (! Schema::hasColumn('pos_sales', 'discount_authorized_by')) $table->unsignedBigInteger('discount_authorized_by')->nullable()->after('discount_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_held_sales');
        Schema::dropIfExists('customers');

        $columns = array_values(array_filter(['customer_id', 'discount_reason', 'discount_authorized_by'], fn ($column) => Schema::hasColumn('pos_sales', $column)));
        if ($columns) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
