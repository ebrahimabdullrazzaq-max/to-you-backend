<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add missing columns
            if (!Schema::hasColumn('users', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('vehicle_type');
            }
            
            if (!Schema::hasColumn('users', 'is_available')) {
                $table->boolean('is_available')->default(false)->after('is_online');
            }
            
            if (!Schema::hasColumn('users', 'rating')) {
                $table->decimal('rating', 3, 2)->default(0.00)->after('is_available');
            }
            
            if (!Schema::hasColumn('users', 'total_orders')) {
                $table->integer('total_orders')->default(0)->after('rating');
            }
            
            if (!Schema::hasColumn('users', 'registration_type')) {
                $table->string('registration_type')->nullable()->after('total_orders');
            }
            
            if (!Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('registration_type');
            }
            
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('profile_photo');
            }
            
            if (!Schema::hasColumn('users', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('bio');
            }
            
            if (!Schema::hasColumn('users', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable()->after('admin_notes');
            }
            
            if (!Schema::hasColumn('users', 'status_changed_by')) {
                $table->foreignId('status_changed_by')->nullable()->constrained('users')->after('status_changed_at');
            }
            
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('status_changed_by');
            }
            
            if (!Schema::hasColumn('users', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->after('phone_verified_at');
            }
            
            if (!Schema::hasColumn('users', 'apple_id')) {
                $table->string('apple_id')->nullable()->after('facebook_id');
            }
            
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('apple_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the columns if needed for rollback
            $columnsToRemove = [
                'is_online',
                'is_available', 
                'rating',
                'total_orders',
                'registration_type',
                'profile_photo',
                'bio',
                'admin_notes',
                'status_changed_at',
                'status_changed_by',
                'phone_verified_at',
                'facebook_id',
                'apple_id',
                'last_login_at'
            ];
            
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};