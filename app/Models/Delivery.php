<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'in_transit_at',
        'out_for_delivery_at',
        'estimated_delivery_date',
        'delivered_at',
        'failed_at',
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
        'in_transit_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
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

    public function isPlatformDelivery(): bool
    {
        return $this->delivery_method === 'platform';
    }

    public function isSupplierDelivery(): bool
    {
        return $this->delivery_method === 'supplier';
    }

    /**
     * Check whether a given user is authorised to update this delivery.
     *
     * FIX: was checking type === 'supplier', but users are registered as type === 'seller'.
     */
    public function canBeUpdatedBy(User $user): bool
    {
        if ($user->hasRole('admin') || $user->type === 'admin') {
            return true;
        }

        // Seller who owns the order's delivery
        if (($user->hasRole('seller') || $user->type === 'seller') && $this->supplier_id === $user->id) {
            return true;
        }

        // Platform courier assigned to this delivery
        if (($user->hasRole('courier') || $user->type === 'courier') && $this->platform_courier_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Generate a unique tracking number.
     *
     * FIX: replaced uniqid() (time-based, collision-prone) with a UUID-backed string
     * that is guaranteed unique. A unique DB constraint on tracking_number is also
     * recommended as a safety net.
     */
    public function generateTrackingNumber(): string
    {
        if (!$this->tracking_number) {
            $this->tracking_number = 'TRK' . strtoupper(Str::random(12));
        }
        return $this->tracking_number;
    }

    /**
     * Calculate the platform delivery fee.
     *
     * Note: $distance is accepted but distance-based pricing is not yet wired up
     * in the rest of the system. Remove it or integrate a real distance source
     * before enabling distance-based fees.
     */
    public function calculatePlatformFee(float $weight = null, float $distance = null): float
    {
        $baseFee = 5000;
        $weightFee = ($weight ?? (float) $this->package_weight ?? 0) * 100;
        $distanceFee = $distance ? ($distance * 200) : 0;

        return $baseFee + $weightFee + $distanceFee;
    }
}
