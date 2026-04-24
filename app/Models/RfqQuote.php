<?php
// app/Models/RfqQuote.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfqQuote extends Model
{
    const STATUS_PENDING   = 'pending';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_WITHDRAWN = 'withdrawn';
    const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'rfq_id',
        'seller_id',
        'unit_price',
        'total_price',
        'currency',
        'delivery_days',
        'validity_days',
        'valid_until',
        'notes',
        'attachments',
        'status',
    ];

    protected $casts = [
        'attachments'   => 'array',
        'unit_price'    => 'decimal:2',
        'total_price'   => 'decimal:2',
        'delivery_days' => 'integer',
        'validity_days' => 'integer',
        'valid_until'   => 'date',
    ];

    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    public function isExpired(): bool
    {
        return $this->valid_until?->isPast() ?? false;
    }
}
