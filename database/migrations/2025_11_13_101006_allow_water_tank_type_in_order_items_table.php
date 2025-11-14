<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Step 1: Drop the existing constraint
        DB::statement("ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_type_check");

        // Step 2: Clean up invalid or NULL type values
        DB::statement("
            UPDATE order_items 
            SET type = 'standard' 
            WHERE type IS NULL 
               OR TRIM(LOWER(type)) NOT IN ('standard', 'custom')
        ");

        // Step 3: Add the new constraint WITH 'water_tank'
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_type_check 
            CHECK (type IN ('standard', 'custom', 'water_tank'))");
    }

    public function down()
    {
        DB::statement("ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_type_check");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_type_check 
            CHECK (type IN ('standard', 'custom'))");
    }
};