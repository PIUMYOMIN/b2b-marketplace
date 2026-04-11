<?php
// app/Models/CodCommissionInvoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CodCommissionInvoice extends Model
{
    protected $fillable = [
        'seller_id', 'order_id', 'invoice_number',
        'order_subtotal', 'commission_rate', 'commission_amount',
        'status', 'due_date',
        'paid_at', 'warning_sent_at', 'suspended_at', 'admin_confirmed_at',
        'confirmed_by', 'payment_reference', 'payment_method',
        'seller_notes', 'admin_notes',
    ];

    protected $casts = [
        'commission_rate'      => 'decimal:4',
        'commission_amount'    => 'decimal:2',
        'order_subtotal'       => 'decimal:2',
        'due_date'             => 'date',
        'paid_at'              => 'datetime',
        'warning_sent_at'      => 'datetime',
        'suspended_at'         => 'datetime',
        'admin_confirmed_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeOutstanding($query)
    {
        return $query->where('status', 'outstanding');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['outstanding', 'overdue']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Generate a unique invoice number: COD-{seller_id}-{year}-{padded_id}
     * Called after create() once ID is available.
     */
    public static function generateNumber(int $sellerId, int $id): string
    {
        return sprintf('COD-%d-%s-%04d', $sellerId, now()->format('Y'), $id);
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->due_date || $this->status === 'paid') return 0;
        return max(0, Carbon::today()->diffInDays($this->due_date, false) * -1);
    }

    public function isOverdue(): bool  { return $this->status === 'overdue'; }
    public function isPaid(): bool     { return $this->status === 'paid'; }
    public function isWaived(): bool   { return $this->status === 'waived'; }
}