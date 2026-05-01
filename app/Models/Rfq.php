<?php
// app/Models/Rfq.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Rfq extends Model
{
    use SoftDeletes;

    const STATUS_DRAFT     = 'draft';
    const STATUS_OPEN      = 'open';
    const STATUS_QUOTED    = 'quoted';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_CLOSED    = 'closed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'rfq_number',
        'buyer_id',
        'product_name',
        'category',
        'quantity',
        'unit',
        'specifications',
        'attachments',
        'budget_min',
        'budget_max',
        'currency',
        'deadline',
        'notes',
        'broadcast',
        'status',
        'accepted_quote_id',
        'order_id',           // ← NEW: FK to the order created on quote acceptance
        'closed_at',
        'expired_at',
    ];

    protected $casts = [
        'attachments'        => 'array',
        'broadcast'          => 'boolean',
        'quantity'           => 'decimal:3',
        'budget_min'         => 'decimal:2',
        'budget_max'         => 'decimal:2',
        'deadline'           => 'date',
        'closed_at'          => 'datetime',
        'expired_at'         => 'datetime',
    ];

    /**
     * Generate a unique sequential RFQ number: RFQ-2026-00001
     */
    public static function generateRfqNumber(): string
    {
        $year   = date('Y');
        $prefix = "RFQ-{$year}-";
        $last   = static::where('rfq_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('rfq_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    // ── Relationships ──────────────────────────────────────────────────────────
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
    public function recipients()
    {
        return $this->hasMany(RfqRecipient::class);
    }
    public function quotes()
    {
        return $this->hasMany(RfqQuote::class)->orderBy('total_price');
    }
    public function acceptedQuote()
    {
        return $this->belongsTo(RfqQuote::class, 'accepted_quote_id');
    }
    public function recipientSellers()
    {
        return $this->belongsToMany(User::class, 'rfq_recipients', 'rfq_id', 'seller_id')
            ->withTimestamps()->withPivot('viewed_at');
    }

    /**
     * The order generated when a quote on this RFQ was accepted.
     * Null until a quote is accepted.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────
    public function scopeOpen($q)
    {
        return $q->where('status', self::STATUS_OPEN);
    }
    public function scopeActive($q)
    {
        return $q->whereIn('status', [self::STATUS_OPEN, self::STATUS_QUOTED]);
    }
    public function scopeForBuyer($q, $userId)
    {
        return $q->where('buyer_id', $userId);
    }

    /**
     * RFQs visible to a given seller — either broadcast OR explicitly invited.
     */
    public function scopeVisibleTo($q, $sellerId)
    {
        return $q->where(function ($qq) use ($sellerId) {
            $qq->where('broadcast', true)
                ->orWhereHas('recipients', fn($r) => $r->where('seller_id', $sellerId));
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_QUOTED]);
    }
    public function isExpired(): bool
    {
        return $this->deadline?->isPast() ?? false;
    }
    public function canReceiveQuotes(): bool
    {
        return $this->isOpen() && !$this->isExpired();
    }
}