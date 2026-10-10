<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sales', 'refunded_amount')) $table->decimal('refunded_amount', 12, 2)->default(0)->after('total_amount');
            if (! Schema::hasColumn('pos_sales', 'voided_at')) $table->timestamp('voided_at')->nullable()->after('completed_at');
            if (! Schema::hasColumn('pos_sales', 'voided_by')) $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            if (! Schema::hasColumn('pos_sales', 'void_authorized_by')) $table->unsignedBigInteger('void_authorized_by')->nullable()->after('voided_by');
            if (! Schema::hasColumn('pos_sales', 'void_reason')) $table->string('void_reason')->nullable()->after('void_authorized_by');
        });

        Schema::table('pos_sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sale_items', 'returned_quantity')) $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
            // Cost at the moment of sale, so profit reports stay correct when
            // the product's cost price changes later.
            if (! Schema::hasColumn('pos_sale_items', 'unit_cost')) $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_payments', 'amount_tendered')) $table->decimal('amount_tendered', 12, 2)->nullable()->after('amount');
            if (! Schema::hasColumn('pos_payments', 'change_given')) $table->decimal('change_given', 12, 2)->nullable()->after('amount_tendered');
        });

        if (! Schema::hasTable('pos_refunds')) {
            Schema::create('pos_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('pharmacy_id')->index();
                $table->string('refund_no')->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('authorized_by')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('method');
                $table->string('reason');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_refund_items')) {
            Schema::create('pos_refund_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_refund_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('pos_sale_item_id')->index();
                $table->unsignedBigInteger('medication_id')->index();
                $table->string('product_name');
                $table->unsignedInteger('quantity');
                $table->decimal('amount', 12, 2);
                $table->boolean('restocked')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->index();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_receipts')) {
            Schema::create('stock_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->index();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('reference_no')->unique();
                $table->string('invoice_no')->nullable();
                $table->date('received_at');
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->string('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_receipt_items')) {
            Schema::create('stock_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('medication_id')->index();
                $table->string('product_name');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_cost', 12, 2)->nullable();
                $table->string('batch_number')->nullable();
                $table->date('expiry_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action')->index(); // e.g. sale.completed, sale.voided, stock.adjusted
                $table->string('description');
                $table->nullableMorphs('subject');
                $table->json('meta')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        $this->backfillLegacySales();
    }

    /**
     * The first POS screen stored one `staff_sales` row per product line and
     * nothing else. Sales screens and reports now read `pos_sales`, so copy
     * those older sales across once — otherwise they would vanish from
     * history. Sales the newer POS already wrote to both tables are skipped.
     */
    protected function backfillLegacySales(): void
    {
        if (! Schema::hasTable('staff_sales')) {
            return;
        }

        $alreadyInPos = DB::table('pos_sales')->select('receipt_no', 'pharmacy_id')->get()
            ->mapWithKeys(fn ($sale) => [$sale->receipt_no . '|' . $sale->pharmacy_id => true]);
        $takenReceiptNos = DB::table('pos_sales')->pluck('receipt_no')->flip();

        $groups = DB::table('staff_sales')->orderBy('id')->get()->groupBy(function ($row) {
            $receiptNo = $row->receipt_no ?? null;

            return $receiptNo ? "{$receiptNo}|{$row->pharmacy_id}|{$row->staff_id}" : "row-{$row->id}";
        });

        foreach ($groups as $lines) {
            $first = $lines->first();
            $receiptNo = ($first->receipt_no ?? null) ?: 'LEGACY-' . $first->id;

            if (isset($alreadyInPos[$receiptNo . '|' . $first->pharmacy_id])) {
                continue;
            }

            if (isset($takenReceiptNos[$receiptNo])) {
                $receiptNo .= '-L' . $first->id;
            }
            $takenReceiptNos[$receiptNo] = true;

            $subtotal = (float) $lines->sum('total_amount');
            $discount = (float) $lines->sum(fn ($line) => (float) ($line->discount_amount ?? 0));
            $total = max(0, $subtotal - $discount);

            DB::transaction(function () use ($lines, $first, $receiptNo, $subtotal, $discount, $total) {
                $saleId = DB::table('pos_sales')->insertGetId([
                    'pharmacy_id' => $first->pharmacy_id,
                    'staff_id' => $first->staff_id,
                    'receipt_no' => $receiptNo,
                    'customer_name' => $first->customer_name ?? null,
                    'customer_phone' => $first->customer_phone ?? null,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'total_amount' => $total,
                    'status' => 'completed',
                    'completed_at' => $first->created_at,
                    'created_at' => $first->created_at,
                    'updated_at' => $first->updated_at,
                ]);

                foreach ($lines as $line) {
                    DB::table('pos_sale_items')->insert([
                        'pos_sale_id' => $saleId,
                        'medication_id' => $line->medication_id,
                        'product_name' => $line->medication_name,
                        'batch_number' => $line->batch_number ?? null,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unit_price,
                        'discount_amount' => (float) ($line->discount_amount ?? 0),
                        'line_total' => $line->total_amount,
                        'created_at' => $line->created_at,
                        'updated_at' => $line->updated_at,
                    ]);
                }

                DB::table('pos_payments')->insert([
                    'pos_sale_id' => $saleId,
                    'method' => ($first->payment_method ?? null) ?: 'cash',
                    'amount' => $total,
                    'status' => 'paid',
                    'created_at' => $first->created_at,
                    'updated_at' => $first->updated_at,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_receipt_items');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('pos_refund_items');
        Schema::dropIfExists('pos_refunds');

        foreach ([
            'pos_payments' => ['amount_tendered', 'change_given'],
            'pos_sale_items' => ['returned_quantity', 'unit_cost'],
            'pos_sales' => ['refunded_amount', 'voided_at', 'voided_by', 'void_authorized_by', 'void_reason'],
        ] as $tableName => $columns) {
            $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($tableName, $column)));
            if ($existing) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
