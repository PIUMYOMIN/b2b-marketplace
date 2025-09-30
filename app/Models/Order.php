<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'total_amount',
        'subtotal_amount',
        'shipping_fee',
        'tax_amount',
        'tax_rate',
        'commission_amount',
        'commission_rate',
        'status',
        'payment_method',
        'payment_status',
        'shipping_address',
        'billing_address',
        'order_notes',
        'tracking_number',
        'shipping_carrier',
        'estimated_delivery',
        'delivered_at',
        'cancelled_at',
        'refund_status',
        'refund_amount',
        'refund_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'estimated_delivery' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'estimated_delivery',
        'delivered_at',
        'cancelled_at',
        'deleted_at',
    ];

    /**
     * Order statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Payment methods
     */
    const PAYMENT_KBZ_PAY = 'kbz_pay';
    const PAYMENT_WAVE_PAY = 'wave_pay';
    const PAYMENT_CB_PAY = 'cb_pay';
    const PAYMENT_CASH_ON_DELIVERY = 'cash_on_delivery';

    /**
     * Payment statuses
     */
    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    /**
     * Refund statuses
     */
    const REFUND_STATUS_NONE = 'none';
    const REFUND_STATUS_REQUESTED = 'requested';
    const REFUND_STATUS_APPROVED = 'approved';
    const REFUND_STATUS_PROCESSED = 'processed';
    const REFUND_STATUS_REJECTED = 'rejected';

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Generate a unique order number.
     *
     * @return string
     */
    public static function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        
        do {
            $random = strtoupper(substr(uniqid(), -6));
            $orderNumber = "{$prefix}{$date}{$random}";
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'order_number';
    }

    /**
     * Relationships
     */

    /**
     * Get the buyer (user) who placed the order.
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller (user) who owns the products.
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the order items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the commission record for the order.
     */
    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    /**
     * Get the payments for the order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the reviews for the order.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Scope queries
     */

    /**
     * Scope a query to only include pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include confirmed orders.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope a query to only include processing orders.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope a query to only include shipped orders.
     */
    public function scopeShipped($query)
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    /**
     * Scope a query to only include delivered orders.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope a query to only include cancelled orders.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope a query to only include paid orders.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    /**
     * Scope a query to only include orders for a specific buyer.
     */
    public function scopeForBuyer($query, $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Scope a query to only include orders for a specific seller.
     */
    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope a query to only include recent orders.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Get the formatted total amount.
     */
    public function getFormattedTotalAttribute()
    {
        return 'MMK ' . number_format($this->total_amount, 0);
    }

    /**
     * Get the formatted subtotal amount.
     */
    public function getFormattedSubtotalAttribute()
    {
        return 'MMK ' . number_format($this->subtotal_amount, 0);
    }

    /**
     * Get the formatted shipping fee.
     */
    public function getFormattedShippingFeeAttribute()
    {
        return 'MMK ' . number_format($this->shipping_fee, 0);
    }

    /**
     * Get the formatted tax amount.
     */
    public function getFormattedTaxAmountAttribute()
    {
        return 'MMK ' . number_format($this->tax_amount, 0);
    }

    /**
     * Get the order status with badge color.
     */
    public function getStatusBadgeAttribute()
    {
        $statusColors = [
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800',
            self::STATUS_PROCESSING => 'bg-indigo-100 text-indigo-800',
            self::STATUS_SHIPPED => 'bg-purple-100 text-purple-800',
            self::STATUS_DELIVERED => 'bg-green-100 text-green-800',
            self::STATUS_CANCELLED => 'bg-red-100 text-red-800',
            self::STATUS_REFUNDED => 'bg-gray-100 text-gray-800',
        ];

        $statusLabels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
        ];

        return [
            'color' => $statusColors[$this->status] ?? 'bg-gray-100 text-gray-800',
            'label' => $statusLabels[$this->status] ?? $this->status,
        ];
    }

    /**
     * Get the payment status with badge color.
     */
    public function getPaymentStatusBadgeAttribute()
    {
        $statusColors = [
            self::PAYMENT_STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::PAYMENT_STATUS_PAID => 'bg-green-100 text-green-800',
            self::PAYMENT_STATUS_FAILED => 'bg-red-100 text-red-800',
            self::PAYMENT_STATUS_REFUNDED => 'bg-gray-100 text-gray-800',
        ];

        $statusLabels = [
            self::PAYMENT_STATUS_PENDING => 'Pending',
            self::PAYMENT_STATUS_PAID => 'Paid',
            self::PAYMENT_STATUS_FAILED => 'Failed',
            self::PAYMENT_STATUS_REFUNDED => 'Refunded',
        ];

        return [
            'color' => $statusColors[$this->payment_status] ?? 'bg-gray-100 text-gray-800',
            'label' => $statusLabels[$this->payment_status] ?? $this->payment_status,
        ];
    }

    /**
     * Get the shipping address as a formatted string.
     */
    public function getFormattedShippingAddressAttribute()
    {
        if (!$this->shipping_address || !is_array($this->shipping_address)) {
            return 'No shipping address provided';
        }

        $address = $this->shipping_address;
        $parts = [
            $address['address'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['postal_code'] ?? '',
            $address['country'] ?? '',
        ];

        return implode(', ', array_filter($parts));
    }

    /**
     * Check if order can be cancelled.
     */
    public function getCanBeCancelledAttribute()
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Check if order can be shipped.
     */
    public function getCanBeShippedAttribute()
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Check if order can be marked as delivered.
     */
    public function getCanBeDeliveredAttribute()
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    /**
     * Check if order can be reviewed.
     */
    public function getCanBeReviewedAttribute()
    {
        return $this->status === self::STATUS_DELIVERED && 
               !$this->reviews()->exists();
    }

    /**
     * Check if order is completed (delivered or cancelled).
     */
    public function getIsCompletedAttribute()
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
        ]);
    }

    /**
     * Business Logic Methods
     */

    /**
     * Calculate commission for this order.
     */
    public function calculateCommission()
    {
        // Get commission rate from product category or use default
        $commissionRate = 0.10; // 10% default commission
        
        // Calculate commission amount
        $this->commission_rate = $commissionRate;
        $this->commission_amount = $this->subtotal_amount * $commissionRate;
        
        return $this->commission_amount;
    }

    /**
     * Update order totals based on items.
     */
    public function updateTotals()
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        $this->subtotal_amount = $subtotal;
        $this->tax_amount = $subtotal * $this->tax_rate;
        $this->total_amount = $subtotal + $this->shipping_fee + $this->tax_amount;
        
        // Recalculate commission if needed
        if ($this->commission_rate > 0) {
            $this->commission_amount = $this->subtotal_amount * $this->commission_rate;
        }

        return $this->save();
    }

    /**
     * Confirm the order.
     */
    public function confirm()
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_CONFIRMED;
        return $this->save();
    }

    /**
     * Mark order as shipped.
     */
    public function ship($trackingNumber = null, $carrier = null)
    {
        if (!$this->can_be_shipped) {
            return false;
        }

        $this->status = self::STATUS_SHIPPED;
        
        if ($trackingNumber) {
            $this->tracking_number = $trackingNumber;
        }
        
        if ($carrier) {
            $this->shipping_carrier = $carrier;
        }

        return $this->save();
    }

    /**
     * Mark order as delivered.
     */
    public function deliver()
    {
        if (!$this->can_be_delivered) {
            return false;
        }

        $this->status = self::STATUS_DELIVERED;
        $this->delivered_at = now();
        return $this->save();
    }

    /**
     * Cancel the order.
     */
    public function cancel($reason = null)
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        
        if ($reason) {
            $this->refund_reason = $reason;
        }

        return $this->save();
    }

    /**
     * Check if payment is required.
     */
    public function requiresPayment()
    {
        return $this->payment_method !== self::PAYMENT_CASH_ON_DELIVERY && 
               $this->payment_status === self::PAYMENT_STATUS_PENDING;
    }

    /**
     * Get the estimated delivery date.
     */
    public function getEstimatedDeliveryDate()
    {
        if ($this->estimated_delivery) {
            return $this->estimated_delivery;
        }

        // Default: 3-7 business days from now
        return now()->addDays(rand(3, 7));
    }
}