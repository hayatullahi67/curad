<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The marketplace order model from the PRD (section 12): one customer Order,
 * its Order Items, and one Pharmacy Fulfillment per pharmacy that supplies
 * part of it. A delivery, when chosen, has one stop per pharmacy.
 *
 * Stock is not copied anywhere: `medications.reserved_stock` only marks how
 * many units of the shared stock are promised to online orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('medications', 'reserved_stock')) {
            Schema::table('medications', function (Blueprint $table) {
                $table->unsignedInteger('reserved_stock')->default(0)->after('stock_level');
            });
        }

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->string('token', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            // A product is the same thing whichever pharmacy sells it, so the
            // cart keeps the product (its name, lower-cased), not one pharmacy's copy.
            $table->string('product_key');
            $table->unsignedBigInteger('medication_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'product_key']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 40)->default('awaiting_confirmation')->index();
            $table->string('fulfillment_method', 20); // pickup | delivery
            $table->decimal('subtotal', 12, 2);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->string('payment_method', 30); // wallet | pay_on_collection
            $table->string('payment_status', 30)->default('pending'); // pending | paid | partially_refunded | refunded
            $table->string('contact_phone', 30)->nullable();
            $table->string('customer_note', 500)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('order_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('pharmacy_id')->index();
            $table->string('status', 30)->default('awaiting_confirmation')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('payout_amount', 12, 2)->default(0);
            $table->boolean('paid_out')->default(false);
            $table->string('pickup_code', 12);
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable(); // rejected or cancelled
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_fulfillment_id')->constrained('order_fulfillments')->cascadeOnDelete();
            $table->unsignedBigInteger('medication_id')->index();
            $table->string('product_name');
            $table->string('dosage')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('order_fulfillment_id')->nullable();
            $table->string('type', 20); // payment | refund
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('paid'); // pending | paid
            $table->string('reference', 40)->unique();
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index(); // pending | picking_up | in_transit | delivered | cancelled
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone', 30);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->string('delivery_code', 12);
            $table->string('rider_name')->nullable();
            $table->string('rider_phone', 30)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivered_by', 30)->nullable(); // pharmacy | customer
            $table->timestamps();
        });

        Schema::create('order_delivery_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_delivery_id')->constrained('order_deliveries')->cascadeOnDelete();
            $table->foreignId('order_fulfillment_id')->constrained('order_fulfillments')->cascadeOnDelete();
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('status', 20)->default('pending'); // pending | collected | skipped
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_delivery_stops');
        Schema::dropIfExists('order_deliveries');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_fulfillments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');

        if (Schema::hasColumn('medications', 'reserved_stock')) {
            Schema::table('medications', fn (Blueprint $table) => $table->dropColumn('reserved_stock'));
        }
    }
};
