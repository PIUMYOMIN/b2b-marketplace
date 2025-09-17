<?php

namespace App\Models;
use App\Models\User;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class SellerReview extends Model
{

    use HasFactory;

    protected $fillable = [
        'seller_id',
        'user_id',
        'rating',
        'comment',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(SellerProfile::class,'seller_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    
}