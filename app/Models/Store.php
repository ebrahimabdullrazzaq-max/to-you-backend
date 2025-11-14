<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

   protected $fillable = [
    'name',
    'category_id',
    'address',
    'city', // 👈 ADD THIS
    'latitude',
    'longitude',
    'phone',
    'image',
    'is_active',
    'total_ratings',
    'rating_sum',
    'average_rating',
    'opening_time',
    'closing_time',
    'is_open',
    'delivery_time_min',
    'delivery_time_max',
    'is_favorite',
    'is_new',
];

    protected $casts = [
        'is_active' => 'boolean',
        'is_open' => 'boolean',
        'average_rating' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function updateRatingStats()
    {
        $this->average_rating = $this->total_ratings > 0 
            ? round($this->rating_sum / $this->total_ratings, 2)
            : 0;
        $this->save();
    }

    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRating($query, $minRating = 0)
    {
        return $query->where('average_rating', '>=', $minRating);
    }


     public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function categories()
{
    return $this->hasMany(StoreCategory::class);
}

public function user()
{
    return $this->belongsTo(\App\Models\User::class);
}


}