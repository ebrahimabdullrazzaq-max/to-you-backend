<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove vehicle_number and vehicle_category columns if they exist
            if (Schema::hasColumn('users', 'vehicle_number')) {
                $table->dropColumn('vehicle_number');
            }
            
            if (Schema::hasColumn('users', 'vehicle_category')) {
                $table->dropColumn('vehicle_category');
            }
            
            // Ensure vehicle_type exists (keep only this)
            if (!Schema::hasColumn('users', 'vehicle_type')) {
                $table->enum('vehicle_type', ['motorcycle', 'car', 'bicycle', 'truck', 'scooter', 'water_truck'])
                      ->nullable()
                      ->after('longitude');
            }
            
            // Ensure other required columns exist
            $requiredColumns = [
                'max_delivery_distance' => ['type' => 'integer', 'default' => 20, 'after' => 'vehicle_type'],
                'availability' => ['type' => 'json', 'nullable' => true, 'after' => 'max_delivery_distance'],
                'registration_type' => ['type' => 'string', 'default' => 'web', 'after' => 'availability'],
            ];
            
            foreach ($requiredColumns as $column => $config) {
                if (!Schema::hasColumn('users', $column)) {
                    if ($config['type'] === 'integer') {
                        $table->integer($column)->default($config['default'])->after($config['after']);
                    } elseif ($config['type'] === 'json') {
                        $table->json($column)->nullable()->after($config['after']);
                    } else {
                        $table->string($column)->default($config['default'])->after($config['after']);
                    }
                }
            }
            
            // Add softDeletes for the SoftDeletes trait
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back the removed columns for rollback
            $table->string('vehicle_number')->nullable()->after('vehicle_type');
            $table->enum('vehicle_category', ['private', 'taxi', 'transport', 'commercial'])->nullable()->after('vehicle_number');
            
            // Remove softDeletes
            $table->dropSoftDeletes();
        });
    }
};