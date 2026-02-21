<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'supplier_id',
        'platform_courier_id',
        'delivery_method',
        'platform_delivery_fee',
        'assigned_driver_name',
        'assigned_driver_phone',
        'assigned_vehicle_type',
        'assigned_vehicle_number',
        'pickup_address',
        'delivery_address',
        'pickup_scheduled_at',
        'picked_up_at',
        'estimated_delivery_date',
        'delivered_at',
        'tracking_number',
        'carrier_name',
        'status',
        'package_weight',
        'package_dimensions',
        'package_count',
        'delivery_proof_image',
        'recipient_name',
        'recipient_phone',
        'recipient_signature',
        'delivery_notes',
        'failure_reason',
        'actual_delivery_cost',
        'delivery_cost_paid',
        'delivery_cost_paid_at',
    ];

    protected $casts = [
        'pickup_scheduled_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_cost_paid_at' => 'datetime',
        'package_dimensions' => 'array',
        'package_weight' => 'decimal:2',
        'platform_delivery_fee' => 'decimal:2',
        'actual_delivery_cost' => 'decimal:2',
        'delivery_cost_paid' => 'boolean',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

     

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function platformCourier()
    {
        return $this->belongsTo(User::class, 'platform_courier_id');
    }

    public function deliveryUpdates()
    {
        return $this->hasMany(DeliveryUpdate::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['awaiting_pickup', 'picked_up', 'in_transit', 'out_for_delivery']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopePlatformDelivery($query)
    {
        return $query->where('delivery_method', 'platform');
    }

    public function scopeSupplierDelivery($query)
    {
        return $query->where('delivery_method', 'supplier');
    }

    // Methods
    public function isPlatformDelivery()
    {
        return $this->delivery_method === 'platform';
    }

    public function isSupplierDelivery()
    {
        return $this->delivery_method === 'supplier';
    }

    public function canBeUpdatedBy(User $user)
    {
        if ($user->type === 'admin') {
            return true;
        }

        if ($user->type === 'supplier' && $this->supplier_id === $user->id) {
            return true;
        }

        if ($user->type === 'courier' && $this->platform_courier_id === $user->id) {
            return true;
        }

        return false;
    }

    public function generateTrackingNumber()
    {
        if (!$this->tracking_number) {
            $this->tracking_number = 'TRK' . strtoupper(uniqid());
        }
        return $this->tracking_number;
    }

    public function calculatePlatformFee($weight = null, $distance = null)
    {
        $baseFee = 5000; // 5000 MMK base fee
        $weightFee = ($weight ?? $this->package_weight ?? 0) * 100; // 100 MMK per kg
        $distanceFee = $distance ? ($distance * 200) : 0; // 200 MMK per km

        return $baseFee + $weightFee + $distanceFee;
    }
}
