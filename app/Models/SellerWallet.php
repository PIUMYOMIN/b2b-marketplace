<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SellerWallet extends Model
{
    protected $fillable = [
        'user_id',
        'escrow_balance',
        'available_balance',
        'total_earned',
        'total_commission_paid',
        'total_withdrawn',
        'cod_commission_outstanding',
    ];

    protected $casts = [
        'escrow_balance'             => 'decimal:2',
        'available_balance'          => 'decimal:2',
        'total_earned'               => 'decimal:2',
        'total_commission_paid'      => 'decimal:2',
        'total_withdrawn'            => 'decimal:2',
        'cod_commission_outstanding' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Get or create wallet for a seller, inside the same DB transaction as the caller.
     */
    public static function forSeller(int $sellerId): self
    {
        return static::firstOrCreate(
            ['user_id' => $sellerId],
            [
                'escrow_balance'             => 0,
                'available_balance'          => 0,
                'total_earned'               => 0,
                'total_commission_paid'      => 0,
                'total_withdrawn'            => 0,
                'cod_commission_outstanding' => 0,
            ]
        );
    }

    /**
     * Lock the wallet row for update (prevents race conditions on concurrent orders).
     */
    public static function lockForSeller(int $sellerId): self
    {
        return static::where('user_id', $sellerId)->lockForUpdate()->firstOrCreate(
            ['user_id' => $sellerId]
        );
    }

    /**
     * Hold funds in escrow when a digital payment is made.
     * Call inside the same DB transaction as Order::create().
     */
    public function holdEscrow(float $amount, int $orderId, ?int $actorId = null): WalletTransaction
    {
        $this->increment('escrow_balance', $amount);
        $this->refresh();

        return $this->transactions()->create([
            'order_id'               => $orderId,
            'type'                   => 'escrow_hold',
            'amount'                 => $amount,
            'escrow_balance_after'   => $this->escrow_balance,
            'available_balance_after'=> $this->available_balance,
            'notes'                  => "Escrow hold: buyer payment received for order #{$orderId}",
            'created_by'             => $actorId,
        ]);
    }

    /**
     * Release escrow on delivery confirmation.
     * Commission is deducted; only seller_payout flows to available_balance.
     */
    public function releaseEscrow(
        float $escrowAmount,
        float $sellerPayout,
        float $commissionAmount,
        int   $orderId,
        ?int  $actorId = null
    ): WalletTransaction {
        $this->decrement('escrow_balance', $escrowAmount);
        $this->increment('available_balance', $sellerPayout);
        $this->increment('total_earned', $sellerPayout);
        $this->increment('total_commission_paid', $commissionAmount);
        $this->refresh();

        return $this->transactions()->create([
            'order_id'               => $orderId,
            'type'                   => 'escrow_release',
            'amount'                 => $sellerPayout,
            'escrow_balance_after'   => $this->escrow_balance,
            'available_balance_after'=> $this->available_balance,
            'notes'                  => "Escrow released: delivery confirmed. "
                                      . "Payout: {$sellerPayout} MMK | Commission kept: {$commissionAmount} MMK",
            'created_by'             => $actorId,
        ]);
    }

    /**
     * Reverse escrow when an order is cancelled before delivery.
     */
    public function reverseEscrow(float $amount, int $orderId, ?int $actorId = null): WalletTransaction
    {
        $this->decrement('escrow_balance', $amount);
        $this->refresh();

        return $this->transactions()->create([
            'order_id'               => $orderId,
            'type'                   => 'escrow_reverse',
            'amount'                 => -$amount,
            'escrow_balance_after'   => $this->escrow_balance,
            'available_balance_after'=> $this->available_balance,
            'notes'                  => "Escrow reversed: order #{$orderId} cancelled",
            'created_by'             => $actorId,
        ]);
    }

    /**
     * Process a refund: deduct from available_balance (post-delivery refund)
     * or from escrow (if still held).
     * Commission is never returned — forfeited amount is recorded separately.
     */
    public function processRefund(
        float $escrowAmount,
        float $buyerRefundAmount,
        float $commissionForfeited,
        int   $orderId,
        ?int  $actorId = null
    ): WalletTransaction {
        // Deduct the full escrow
        $this->decrement('escrow_balance', $escrowAmount);
        // Commission stays — do not credit anything to seller available_balance
        $this->refresh();

        return $this->transactions()->create([
            'order_id'               => $orderId,
            'type'                   => 'refund_hold',
            'amount'                 => -$buyerRefundAmount,
            'escrow_balance_after'   => $this->escrow_balance,
            'available_balance_after'=> $this->available_balance,
            'notes'                  => "Refund processed for order #{$orderId}. "
                                      . "Buyer refund: {$buyerRefundAmount} MMK | "
                                      . "Commission forfeited (non-refundable): {$commissionForfeited} MMK",
            'created_by'             => $actorId,
        ]);
    }
}
