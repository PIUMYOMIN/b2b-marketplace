<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'order_id',
        'type',
        'amount',
        'escrow_balance_after',
        'available_balance_after',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'                  => 'decimal:2',
        'escrow_balance_after'    => 'decimal:2',
        'available_balance_after' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function wallet()
    {
        return $this->belongsTo(SellerWallet::class, 'wallet_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getIsDebitAttribute(): bool
    {
        return $this->amount < 0;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'escrow_hold'    => 'Payment Held in Escrow',
            'escrow_release' => 'Payout Released',
            'escrow_reverse' => 'Escrow Reversed (Cancelled)',
            'commission_deduct' => 'Commission Deducted',
            'refund_hold'    => 'Refund Processed',
            'withdrawal'     => 'Withdrawal',
            'cod_invoice'    => 'COD Commission Invoiced',
            'cod_payment'    => 'COD Commission Paid',
            'adjustment'     => 'Manual Adjustment',
            default          => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
