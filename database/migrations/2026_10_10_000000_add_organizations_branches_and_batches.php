<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD sections 5-8: a pharmacy business is an Organization with one or more
 * Branches. Every `pharmacies` row is a branch; everything that was already
 * recorded per pharmacy (stock, sales, shifts, orders) is therefore already
 * per branch. This adds what was missing around it:
 *
 *   - organizations, and which organization each branch belongs to
 *   - products: the organization's catalogue, one row per product, which
 *     each branch's stock row (medications) points at
 *   - inventory_batches: stock split by batch and expiry date
 *   - stock_transfers: stock moved from one branch to another
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->index()->after('user_id');
            $table->boolean('is_main')->default(false)->after('organization_id');
            $table->boolean('is_active')->default(true)->after('is_main');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('name');
            // Name and pack size, lower-cased: how the same product is recognised across branches.
            $table->string('match_key', 300);
            $table->string('category')->nullable();
            $table->string('dosage')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_rx')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'match_key']);
        });

        Schema::table('medications', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->index()->after('pharmacy_id');
            // Units still on the shelf whose batch has passed its expiry date. Never sold.
            $table->unsignedInteger('expired_stock')->default(0)->after('reserved_stock');
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->index();
            $table->foreignId('medication_id')->constrained('medications')->cascadeOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->date('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('reference', 30)->unique();
            $table->unsignedBigInteger('from_pharmacy_id')->index();
            $table->unsignedBigInteger('to_pharmacy_id')->index();
            $table->string('status', 20)->default('in_transit'); // in_transit | received | cancelled
            $table->string('note')->nullable();
            $table->unsignedBigInteger('sent_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('from_medication_id');
            $table->unsignedBigInteger('to_medication_id')->nullable();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            // The batches the units came from, so they arrive with the same expiry dates.
            $table->json('batches')->nullable();
            $table->timestamps();
        });

        $this->moveExistingData();
    }

    /** Each pharmacy becomes the main branch of its own organization; its products and stock follow. */
    private function moveExistingData(): void
    {
        $now = now();

        foreach (DB::table('pharmacies')->orderBy('id')->get() as $pharmacy) {
            $organizationId = DB::table('organizations')->where('owner_id', $pharmacy->user_id)->value('id');
            $isFirst = $organizationId === null;

            $organizationId ??= DB::table('organizations')->insertGetId([
                'owner_id' => $pharmacy->user_id,
                'name' => $pharmacy->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('pharmacies')->where('id', $pharmacy->id)->update(['organization_id' => $organizationId, 'is_main' => $isFirst]);

            foreach (DB::table('medications')->where('pharmacy_id', $pharmacy->id)->orderBy('id')->get() as $row) {
                $key = mb_strtolower(trim($row->name)) . '|' . mb_strtolower(trim((string) $row->dosage));
                $productId = DB::table('products')->where('organization_id', $organizationId)->where('match_key', $key)->value('id');

                $productId ??= DB::table('products')->insertGetId([
                    'organization_id' => $organizationId,
                    'name' => $row->name,
                    'match_key' => $key,
                    'category' => $row->category,
                    'dosage' => $row->dosage,
                    'barcode' => $row->barcode,
                    'description' => $row->description,
                    'photo' => $row->photo,
                    'is_rx' => (bool) $row->is_rx,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $expired = $row->expiry_date && $row->expiry_date < $now->toDateString();

                DB::table('medications')->where('id', $row->id)->update([
                    'product_id' => $productId,
                    'expired_stock' => $expired ? max(0, (int) $row->stock_level) : 0,
                ]);

                // What is on the shelf today becomes that product's first batch.
                if ((int) $row->stock_level > 0) {
                    DB::table('inventory_batches')->insert([
                        'pharmacy_id' => $pharmacy->id,
                        'medication_id' => $row->id,
                        'batch_number' => $row->batch_number,
                        'expiry_date' => $row->expiry_date,
                        'quantity' => (int) $row->stock_level,
                        'received_at' => $now->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Staff who were never tied to a pharmacy belong to the one their owner runs.
            DB::table('users')
                ->where('cura_role', 'sales_person')
                ->where('created_by_user_id', $pharmacy->user_id)
                ->whereNull('assigned_pharmacy_id')
                ->update(['assigned_pharmacy_id' => $pharmacy->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('inventory_batches');

        Schema::table('medications', fn (Blueprint $table) => $table->dropColumn(['product_id', 'expired_stock']));
        Schema::dropIfExists('products');
        Schema::table('pharmacies', fn (Blueprint $table) => $table->dropColumn(['organization_id', 'is_main', 'is_active']));
        Schema::dropIfExists('organizations');
    }
};
