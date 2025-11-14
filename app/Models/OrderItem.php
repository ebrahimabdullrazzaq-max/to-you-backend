<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'custom_name', // ✅ FOR CUSTOM ORDERS
        'quantity',
        'price',
        'special_instructions',
        'type', // ✅ 'custom' OR 'product'
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ✅ ADD METHOD TO CHECK IF ITEM IS CUSTOM
    public function isCustom()
    {
        return $this->type === 'custom' || !empty($this->custom_name);
    }

    // ✅ ADD METHOD TO CHECK IF ITEM IS PRODUCT
    public function isProduct()
    {
        return $this->type === 'product' && !empty($this->product_id);
    }
}