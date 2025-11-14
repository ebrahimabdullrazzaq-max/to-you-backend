<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Step 1: Drop the existing constraint if it exists
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_vehicle_type_check");

        // Step 2: Change the column type to TEXT (to avoid ENUM limitations) OR add 'water_truck' to ENUM
        // Option A: Convert to TEXT (recommended for flexibility)
        DB::statement("ALTER TABLE users ALTER COLUMN vehicle_type TYPE TEXT");

        // Option B: If you want to keep ENUM, you must redefine the entire enum type
        // But since we can't easily do that without knowing the current type, TEXT is safer.

        // Step 3: Add a new CHECK constraint if desired (optional)
        // DB::statement("ALTER TABLE users ADD CONSTRAINT users_vehicle_type_check 
        //     CHECK (vehicle_type IN ('motorcycle', 'car', 'bicycle', 'truck', 'scooter', 'water_truck'))");
    }

    public function down()
    {
        // Revert to original (if needed)
        DB::statement("ALTER TABLE users ALTER COLUMN vehicle_type TYPE VARCHAR(50)");
        // Optionally add back a constraint
        // DB::statement("ALTER TABLE users ADD CONSTRAINT users_vehicle_type_check 
        //     CHECK (vehicle_type IN ('motorcycle', 'car', 'bicycle', 'truck', 'scooter'))");
    }
};