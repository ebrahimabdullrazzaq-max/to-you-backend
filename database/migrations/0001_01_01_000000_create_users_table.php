<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable(); // ✅ Changed to nullable for Google users
            $table->enum('role', ['admin', 'employer', 'customer'])->default('customer');
            
            // ✅ FIXED: Better status handling
            $table->enum('status', ['active', 'pending', 'approved', 'rejected'])->default('active');
            
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('vehicle_type')->nullable(); // ✅ Keep only vehicle_type
            // REMOVED: vehicle_number column
            // REMOVED: vehicle_category column
            $table->integer('max_delivery_distance')->default(20);
            $table->json('availability')->nullable();
            $table->string('registration_type')->nullable();
            $table->string('profile_photo')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(false);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_orders')->default(0);
            $table->text('admin_notes')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('apple_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // ✅ Added for SoftDeletes
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};