<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 🔥 Step 1: DROP the constraint FIRST
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_type_check");

        // ✅ Step 2: Now safely clean data
        DB::statement("
            UPDATE orders 
            SET order_type = 'standard' 
            WHERE order_type IS NULL 
               OR TRIM(LOWER(order_type)) NOT IN ('standard', 'custom')
        ");

        // ✅ Step 3: Add the new constraint with 'water_tank'
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_type_check 
            CHECK (order_type IN ('standard', 'custom', 'water_tank'))");
    }

    public function down()
    {
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_type_check");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_type_check 
            CHECK (order_type IN ('standard', 'custom'))");
    }
};