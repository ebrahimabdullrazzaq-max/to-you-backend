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
        Schema::table('users', function (Blueprint $table) {
            // REMOVED: vehicle_category column addition
            
            // REMOVED: vehicle_number length update
            
            // Add vehicle_type with water_truck if needed (keep only vehicle_type)
            if (!Schema::hasColumn('users', 'vehicle_type')) {
                $table->enum('vehicle_type', ['motorcycle', 'car', 'bicycle', 'truck', 'scooter', 'water_truck'])
                      ->nullable()
                      ->after('longitude');
            }
            
            // Add other missing columns (keep these as they're still needed)
            $columnsToAdd = [
                'max_delivery_distance' => ['type' => 'integer', 'default' => 20, 'after' => 'vehicle_type'], // ✅ Changed after to vehicle_type
                'availability' => ['type' => 'json', 'after' => 'max_delivery_distance'],
                'registration_type' => ['type' => 'enum:web,employer_app', 'default' => 'web', 'after' => 'availability'],
            ];
            
            foreach ($columnsToAdd as $column => $config) {
                if (!Schema::hasColumn('users', $column)) {
                    if ($config['type'] === 'integer') {
                        $table->integer($column)->nullable()->default($config['default'])->after($config['after']);
                    } elseif ($config['type'] === 'json') {
                        $table->json($column)->nullable()->after($config['after']);
                    } elseif (str_starts_with($config['type'], 'enum:')) {
                        $values = explode(',', str_replace('enum:', '', $config['type']));
                        $table->enum($column, $values)->default($config['default'])->after($config['after']);
                    }
                }
            }

            // ✅ ADD: softDeletes if not exists
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the columns we added in this migration
            $columnsToRemove = [
                'vehicle_type',
                'max_delivery_distance', 
                'availability',
                'registration_type',
                'deleted_at'
            ];
            
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};