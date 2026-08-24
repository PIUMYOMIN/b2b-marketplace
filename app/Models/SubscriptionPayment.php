<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'order_id',
        'amount_mmk',
        'payment_method',
        'status',
        'gateway_reference',
        'paid_at',
        'raw_payload',
    ];

    protected $casts = [
        'amount_mmk'  => 'decimal:2',
        'paid_at'     => 'datetime',
        'raw_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
