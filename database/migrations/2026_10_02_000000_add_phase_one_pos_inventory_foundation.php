<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            if (! Schema::hasColumn('medications', 'sku')) $table->string('sku')->nullable()->index()->after('name');
            if (! Schema::hasColumn('medications', 'barcode')) $table->string('barcode')->nullable()->index()->after('sku');
            if (! Schema::hasColumn('medications', 'cost_price')) $table->decimal('cost_price', 12, 2)->nullable()->after('price');
            if (! Schema::hasColumn('medications', 'batch_number')) $table->string('batch_number')->nullable()->after('stock_level');
            if (! Schema::hasColumn('medications', 'is_marketplace_available')) $table->boolean('is_marketplace_available')->default(true)->after('expiry_date');
        });

        // These columns were already used by the first POS screen, but were
        // not included in its original migration. Keeping the legacy line
        // records available makes this upgrade safe for existing pharmacies.
        Schema::table('staff_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_sales', 'receipt_no')) $table->string('receipt_no')->nullable()->index()->after('pharmacy_id');
            if (! Schema::hasColumn('staff_sales', 'batch_number')) $table->string('batch_number')->nullable()->after('medication_name');
            if (! Schema::hasColumn('staff_sales', 'is_rx')) $table->boolean('is_rx')->default(false)->after('batch_number');
            if (! Schema::hasColumn('staff_sales', 'customer_name')) $table->string('customer_name')->nullable()->after('total_amount');
            if (! Schema::hasColumn('staff_sales', 'customer_phone')) $table->string('customer_phone')->nullable()->after('customer_name');
            if (! Schema::hasColumn('staff_sales', 'payment_method')) $table->string('payment_method')->nullable()->after('customer_phone');
            if (! Schema::hasColumn('staff_sales', 'discount_amount')) $table->decimal('discount_amount', 12, 2)->default(0)->after('payment_method');
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->index();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('receipt_no')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('completed')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('medication_id')->index();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('batch_number')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('paid');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->index();
            $table->unsignedBigInteger('medication_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type')->index(); // opening_stock, received, sale, adjustment, expiry, reservation
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('reason')->nullable();
            $table->nullableMorphs('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::table('medications', function (Blueprint $table) {
            $columns = ['sku', 'barcode', 'cost_price', 'batch_number', 'is_marketplace_available'];
            $existing = array_filter($columns, fn ($column) => Schema::hasColumn('medications', $column));
            if ($existing) $table->dropColumn($existing);
        });
    }
};
