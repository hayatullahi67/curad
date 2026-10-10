<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_LABELS = [
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_VOIDED => 'Voided',
        self::STATUS_PARTIALLY_REFUNDED => 'Part refunded',
        self::STATUS_REFUNDED => 'Refunded',
    ];

    protected $fillable = [
        'pharmacy_id', 'staff_id', 'shift_id', 'customer_id', 'receipt_no', 'customer_name', 'customer_phone',
        'discount_reason', 'discount_authorized_by',
        'subtotal', 'discount_amount', 'total_amount', 'refunded_amount', 'status', 'completed_at',
        'voided_at', 'voided_by', 'void_authorized_by', 'void_reason',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'voided_at' => 'datetime',
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'total_amount' => 'float',
        'refunded_amount' => 'float',
    ];

    public function items() { return $this->hasMany(PosSaleItem::class); }
    public function payments() { return $this->hasMany(PosPayment::class); }
    public function refunds() { return $this->hasMany(PosRefund::class); }
    public function staff() { return $this->belongsTo(User::class, 'staff_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function discountAuthorizedBy() { return $this->belongsTo(User::class, 'discount_authorized_by'); }
    public function voidedBy() { return $this->belongsTo(User::class, 'voided_by'); }
    public function voidAuthorizedBy() { return $this->belongsTo(User::class, 'void_authorized_by'); }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isSplitPayment(): bool
    {
        return $this->payments->count() > 1;
    }

    /** "Cash", or "Split: Cash + Bank Transfer" when paid more than one way. */
    public function paymentLabel(): string
    {
        $labels = $this->payments->map(fn (PosPayment $payment) => PosPayment::methodLabel($payment->method));

        return $labels->count() > 1 ? 'Split: ' . $labels->implode(' + ') : ($labels->first() ?? 'Cash');
    }

    /**
     * Everything the till needs to show, print or share this sale's
     * receipt. Used right after the sale and again for a reprint.
     */
    public function receiptData(Pharmacy $pharmacy): array
    {
        $this->loadMissing('items', 'payments', 'staff');
        $staff = $this->staff;

        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'pharmacy' => [
                'name' => $pharmacy->name,
                'address' => $pharmacy->address ?? '',
                'phone' => $pharmacy->phone ?? '',
            ],
            'cashier' => [
                'name' => $staff->full_name ?? 'Cashier',
                'role' => $staff && $staff->role === 'vendor' ? 'Owner' : \App\Support\StaffRoles::label($staff->staff_role ?? null),
            ],
            'customer' => [
                'name' => $this->customer_name ?: 'Walk-in Customer',
                'phone' => $this->customer_phone ?? '',
            ],
            'items' => $this->items->map(fn (PosSaleItem $item) => [
                'name' => $item->product_name,
                'sku' => $item->sku ?? '',
                'batch_number' => $item->batch_number ?? '',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->line_total,
            ])->all(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount_amount,
            'net_total' => $this->total_amount,
            'refunded_amount' => $this->refunded_amount,
            'payment_method' => $this->paymentLabel(),
            'payments' => $this->payments->map(fn (PosPayment $payment) => [
                'method' => $payment->method,
                'label' => PosPayment::methodLabel($payment->method),
                'amount' => $payment->amount,
                'tendered' => $payment->amount_tendered ?? $payment->amount,
                'reference' => $payment->reference ?? '',
            ])->all(),
            'amount_tendered' => round($this->payments->sum(fn (PosPayment $payment) => $payment->amount_tendered ?? $payment->amount), 2),
            'change_due' => round($this->payments->sum('change_given'), 2),
        ];
    }

    /**
     * What the pharmacy actually kept from this sale: nothing if it was
     * voided, otherwise the amount paid less anything refunded since.
     */
    public function netAmount(): float
    {
        return $this->isVoided() ? 0.0 : max(0, $this->total_amount - $this->refunded_amount);
    }

    /**
     * A void cancels the whole sale as if it never happened, so it is only
     * for same-day mistakes on a sale that has not been partly refunded.
     * Anything older goes through a refund, which keeps the original sale
     * on record.
     */
    public function canBeVoided(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->refunded_amount <= 0
            && $this->created_at->isToday();
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIALLY_REFUNDED], true)
            && $this->refunded_amount < $this->total_amount;
    }

    /**
     * Next receipt number for a pharmacy: CURA-{pharmacy}-{yymmdd}-{0001}.
     * A running number per pharmacy per day is readable at the counter and,
     * unlike a random suffix, cannot repeat on a busy day. Two tills asking
     * at the same instant can still get the same number, which is why the
     * caller retries when the unique index rejects it.
     */
    public static function nextReceiptNo(int $pharmacyId): string
    {
        $prefix = 'CURA-' . $pharmacyId . '-' . now()->format('ymd') . '-';

        $last = static::where('receipt_no', 'like', $prefix . '%')->orderByDesc('id')->value('receipt_no');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
