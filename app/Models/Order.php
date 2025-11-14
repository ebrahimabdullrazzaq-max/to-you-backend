<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id', // ✅ Keep this but allow null for delivery orders
        'employer_id',
        'address',
        'latitude',
        'longitude',
        'status',
        'subtotal',
        'delivery_fee',
        'total',
        'payment_method',
        'notes',
        'phone',
        'rating',
        'review',
        'rated_at',
        'is_rated',
        'confirmed_at',
        'preparing_at',
        'on_the_way_at',
        'delivered_at',
        'canceled_at',
        'assigned_at',
        // ✅ DELIVERY TRACKING FIELDS
        'delivery_current_lat',
        'delivery_current_lng',
        'delivery_updated_at',
        // ✅ ADD THESE CUSTOM ORDER FIELDS
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'order_type',
    ];

    protected $casts = [
        'is_rated' => 'boolean',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'preparing_at' => 'datetime',
        'on_the_way_at' => 'datetime',
        'delivered_at' => 'datetime',
        'canceled_at' => 'datetime',
        'rated_at' => 'datetime',
        'assigned_at' => 'datetime',
        // ✅ DELIVERY FIELDS CASTS
        'delivery_updated_at' => 'datetime',
        // ✅ ADD CASTS FOR CUSTOM ORDER FIELDS
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class)->withDefault([
            'name' => 'Direct Delivery Service',
            'address' => 'Pickup Location',
        ]);
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeRated($query)
    {
        return $query->whereNotNull('rating');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    // ✅ ADD SCOPES FOR DIFFERENT ORDER TYPES
    public function scopeCustomOrders($query)
    {
        return $query->where('order_type', 'custom_order');
    }

    public function scopeDeliveryOrders($query)
    {
        return $query->where('order_type', 'custom_delivery');
    }

    public function scopeRegularOrders($query)
    {
        return $query->where('order_type', 'regular');
    }

    public function updateStatus($status)
    {
        $this->status = $status;
        
        switch ($status) {
            case 'confirmed':
                $this->confirmed_at = now();
                break;
            case 'preparing':
                $this->preparing_at = now();
                break;
            case 'on_the_way':
                $this->on_the_way_at = now();
                break;
            case 'delivered':
                $this->delivered_at = now();
                break;
            case 'canceled':
                $this->canceled_at = now();
                break;
        }

        $this->save();
    }

    // ✅ ADD METHOD TO UPDATE DELIVERY LOCATION
    public function updateDeliveryLocation($latitude, $longitude)
    {
        $this->delivery_current_lat = $latitude;
        $this->delivery_current_lng = $longitude;
        $this->delivery_updated_at = now();
        $this->save();
    }

    // ✅ ADD METHOD TO CHECK IF ORDER IS DELIVERY
    public function isDeliveryOrder()
    {
        return $this->order_type === 'custom_delivery';
    }

    // ✅ ADD METHOD TO CHECK IF ORDER IS CUSTOM
    public function isCustomOrder()
    {
        return $this->order_type === 'custom_order';
    }
}