<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    protected $fillable = [
        'seller_id',
        'amount',
        'status',
        'payment_method',
        'bank_name',
        'account_name',
        'account_number',
        'seller_note',
        'admin_note',
        'admin_withdrawal_reference',
        'seller_transfer_reference',
        'processed_by',
        'approved_at',
        'admin_withdrawn_at',
        'paid_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'admin_withdrawn_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
