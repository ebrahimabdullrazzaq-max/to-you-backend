<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // For PostgreSQL
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_type_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_type_check CHECK (order_type IN ('regular', 'custom_order', 'custom_delivery'))");
        
        // Alternative for MySQL (if you're using MySQL):
        // DB::statement("ALTER TABLE orders DROP CHECK orders_order_type_check");
        // DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_type_check CHECK (order_type IN ('regular', 'custom_order', 'custom_delivery'))");
    }

    public function down()
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_type_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_type_check CHECK (order_type IN ('regular', 'custom_order'))");
    }
};