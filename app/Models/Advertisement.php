<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advertisement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'target_url',
        'is_active',
        'start_date',
        'end_date',
        'priority',
        'target_cities',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'target_cities' => 'array',
        'priority' => 'integer',
    ];

    // ✅ Make sure this relationship exists
    public function admin()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ✅ Add these scopes if they don't exist
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->where('start_date', '<=', now())
                          ->orWhereNull('start_date');
                    })
                    ->where(function($q) {
                        $q->where('end_date', '>=', now())
                          ->orWhereNull('end_date');
                    });
    }

    public function scopeForCity($query, $city)
    {
        return $query->where(function($q) use ($city) {
            $q->whereNull('target_cities')
              ->orWhereJsonContains('target_cities', $city);
        });
    }
}