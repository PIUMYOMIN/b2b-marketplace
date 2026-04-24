<?php
// app/Models/RfqRecipient.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfqRecipient extends Model
{
    protected $fillable = ['rfq_id', 'seller_id', 'viewed_at'];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
