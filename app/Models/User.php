<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'latitude',
        'longitude',
        'role',
        'status',
        'google_id',
        'vehicle_type',
        'max_delivery_distance',
        'availability',
        'registration_type',
        'profile_photo',
        'bio',
        'is_online',
        'is_available',
        'rating',
        'total_orders',
        'admin_notes',
        'status_changed_at',
        'status_changed_by',
        'phone_verified_at',
        'facebook_id',
        'apple_id',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'facebook_id',
        'apple_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'max_delivery_distance' => 'integer',
        'availability' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'rating' => 'decimal:2',
        'total_orders' => 'integer',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'role' => 'customer',
        'is_online' => false,
        'is_available' => false,
        'rating' => 0.00,
        'total_orders' => 0,
        'max_delivery_distance' => 20,
        'registration_type' => 'email',
        'profile_photo' => null,
        'bio' => null,
    ];

    protected $appends = [
        'vehicle_type_display',
        'full_address',
        'availability_summary',
        'can_accept_orders',
        'is_admin',
        'profile_photo_url',
        'registration_completed',
    ];

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->status)) {
                $user->status = $user->role === 'customer' ? 'approved' : 'pending';
            }
            if (empty($user->role)) {
                $user->role = 'customer';
            }
        });

        static::updating(function ($user) {
            if ($user->isDirty('status') || $user->isDirty('is_online')) {
                $user->is_available = $user->canAcceptOrders() && $user->is_online;
            }
        });
    }

    // Relationships
    public function address()
    {
        return $this->hasOne(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveredOrders()
    {
        return $this->hasMany(Order::class, 'employer_id');
    }

    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    // Scopes
    public function scopePendingEmployers($query)
    {
        return $query->where('role', 'employer')->where('status', 'pending');
    }

    public function scopeApprovedEmployers($query)
    {
        return $query->where('role', 'employer')->where('status', 'approved');
    }

    public function scopeActiveEmployers($query)
    {
        return $query->where('role', 'employer')->where('status', 'active');
    }

    public function scopeRejectedEmployers($query)
    {
        return $query->where('role', 'employer')->where('status', 'rejected');
    }

    public function scopeAvailableEmployers($query)
    {
        return $query->where('role', 'employer')
                    ->where('status', 'active')
                    ->where('is_online', true)
                    ->where('is_available', true)
                    ->whereNotNull('vehicle_type');
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeWithVerifiedEmail($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeWithVerifiedPhone($query)
    {
        return $query->whereNotNull('phone_verified_at');
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('email', 'like', "%{$searchTerm}%")
              ->orWhere('phone', 'like', "%{$searchTerm}%");
        });
    }

    public function scopeWithTrashed($query)
    {
        return $query->withTrashed();
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->onlyTrashed();
    }

    // Check methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isEmployer()
    {
        return $this->role === 'employer';
    }

    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    public function isApproved()
    {
        return in_array($this->status, ['approved', 'active']);
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    public function hasVerifiedPhone()
    {
        return !is_null($this->phone_verified_at);
    }

    // Accessors
    public function getIsAdminAttribute()
    {
        return $this->isAdmin();
    }

    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo) {
            return asset('storage/' . $this->profile_photo);
        }
        
        return "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&color=7F9CF5&background=EBF4FF";
    }

    public function getRegistrationCompletedAttribute()
    {
        return $this->isRegistrationCompleted();
    }

    public function getVehicleTypeDisplayAttribute()
    {
        $vehicleTypes = [
            'motorcycle' => ['name' => 'Motorcycle', 'emoji' => '🏍️'],
            'car' => ['name' => 'Car', 'emoji' => '🚗'],
            'bicycle' => ['name' => 'Bicycle', 'emoji' => '🚲'],
            'truck' => ['name' => 'Truck', 'emoji' => '🚚'],
            'scooter' => ['name' => 'Scooter', 'emoji' => '🛵'],
            'water_truck' => ['name' => 'Water Truck', 'emoji' => '💧🚚'],
        ];

        $type = $this->vehicle_type;
        $vehicle = $vehicleTypes[$type] ?? ['name' => 'Unknown', 'emoji' => '🚗'];
        
        return $vehicle['name'] . ' ' . $vehicle['emoji'];
    }

    public function getCanAcceptOrdersAttribute()
    {
        return $this->canAcceptOrders();
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->latitude ? "Lat: {$this->latitude}" : null,
            $this->longitude ? "Lng: {$this->longitude}" : null,
        ]);

        return implode(', ', $parts) ?: 'No address provided';
    }

    public function getAvailabilitySummaryAttribute()
    {
        $availability = $this->availability;
        
        if (empty($availability) || empty($availability['days'])) {
            return 'Not set';
        }

        $days = $availability['days'] ?? [];
        $startTime = $availability['start_time'] ?? 'Not set';
        $endTime = $availability['end_time'] ?? 'Not set';

        $dayNames = [
            'monday' => 'Mon',
            'tuesday' => 'Tue', 
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun',
        ];

        $selectedDays = array_map(function($day) use ($dayNames) {
            return $dayNames[$day] ?? ucfirst($day);
        }, $days);

        if (empty($selectedDays)) {
            return 'No working days set';
        }

        return count($selectedDays) . ' days: ' . implode(', ', $selectedDays) . ' | ' . $startTime . ' - ' . $endTime;
    }

    // Business Logic Methods
    public function canAcceptOrders()
    {
        return $this->isEmployer() && 
               $this->isActive() && 
               $this->hasVerifiedEmail() &&
               $this->is_online &&
               $this->is_available &&
               !empty($this->vehicle_type);
    }

    public function isRegistrationCompleted()
    {
        if ($this->isCustomer()) {
            return !empty($this->address) && !empty($this->phone);
        }
        
        if ($this->isEmployer()) {
            return !empty($this->address) && 
                   !empty($this->phone) && 
                   !empty($this->vehicle_type);
        }
        
        return true;
    }

    public function updateLastActivity()
    {
        $this->update([
            'last_login_at' => now(),
            'is_online' => true
        ]);
    }

    // Email Verification
    public function markEmailAsVerified()
    {
        $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();

        if ($this->isCustomer() && $this->isPending()) {
            $this->update(['status' => 'approved']);
        }
    }

    public function markPhoneAsVerified()
    {
        $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
    }

    // Availability handling
    public function getAvailabilityAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        
        $decoded = json_decode($value, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [
                'days' => [],
                'start_time' => '08:00',
                'end_time' => '18:00'
            ];
        }
        
        return $decoded;
    }

    public function setAvailabilityAttribute($value)
    {
        if (is_array($value)) {
            $defaultAvailability = [
                'days' => $value['days'] ?? [],
                'start_time' => $value['start_time'] ?? '08:00',
                'end_time' => $value['end_time'] ?? '18:00'
            ];
            $this->attributes['availability'] = json_encode($defaultAvailability);
        } else {
            $this->attributes['availability'] = $value;
        }
    }

    // Status Management
    public function setOnlineStatus($online)
    {
        $this->update([
            'is_online' => $online,
            'is_available' => $online && $this->canAcceptOrders()
        ]);
    }

    public function approve($adminId = null, $notes = null)
    {
        $this->update([
            'status' => 'active',
            'status_changed_at' => now(),
            'status_changed_by' => $adminId,
            'admin_notes' => $notes,
            'is_available' => true
        ]);
    }

    public function reject($adminId = null, $reason = null)
    {
        $this->update([
            'status' => 'rejected',
            'status_changed_at' => now(),
            'status_changed_by' => $adminId,
            'admin_notes' => $reason,
            'is_online' => false,
            'is_available' => false
        ]);
    }

    public function suspend($adminId = null, $reason = null)
    {
        $this->update([
            'status' => 'suspended',
            'status_changed_at' => now(),
            'status_changed_by' => $adminId,
            'admin_notes' => $reason,
            'is_online' => false,
            'is_available' => false
        ]);
    }

    public function updateRating($newRating)
    {
        $currentTotal = $this->rating * $this->total_orders;
        $this->total_orders += 1;
        $this->rating = ($currentTotal + $newRating) / $this->total_orders;
        $this->save();
    }

    // Static Methods
    public static function getValidationRules($type = 'create', $userId = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
        ];

        if ($type === 'create') {
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['email'] = 'required|email|max:255|unique:users';
        } else {
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $userId;
        }

        return $rules;
    }
}