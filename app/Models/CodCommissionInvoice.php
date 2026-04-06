<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CodCommissionInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'order_id',
        'seller_id',
        'order_subtotal',
        'commission_rate',
        'commission_amount',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        'payment_method',
        'confirmed_by',
        'confirmed_at',
        'admin_notes',
        'seller_notes',
    ];

    protected $casts = [
        'order_subtotal'    => 'decimal:2',
        'commission_rate'   => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'due_date'          => 'date',
        'paid_at'           => 'datetime',
        'confirmed_at'      => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function confirmedByAdmin()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeOutstanding($q)
    {
        return $q->whereIn('status', ['outstanding', 'overdue']);
    }

    public function scopeOverdue($q)
    {
        return $q->where('status', 'outstanding')
                 ->where('due_date', '<', now()->toDateString());
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'outstanding'
            && $this->due_date->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'outstanding' => $this->is_overdue ? 'Overdue' : 'Outstanding',
            'paid'        => 'Paid',
            'overdue'     => 'Overdue',
            'waived'      => 'Waived',
            default       => ucfirst($this->status),
        };
    }

    /**
     * Generate the next sequential invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $count = static::withTrashed()->count() + 1;
        return 'COD-INV-' . date('Ymd') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
