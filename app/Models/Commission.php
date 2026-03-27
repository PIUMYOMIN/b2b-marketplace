<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'amount',
        'commission_rate',
        'tax_amount',
        'tax_rate',
        'platform_revenue',
        'seller_payout',
        'status',
        'due_date',
        'collected_at',
        'notes',
        'commission_rule_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'platform_revenue' => 'decimal:2',
        'seller_payout' => 'decimal:2',
        'due_date' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rule()
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}