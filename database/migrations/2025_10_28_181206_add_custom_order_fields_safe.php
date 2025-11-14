<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add fields to orders table ONLY if they don't exist
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pickup_address')) {
                $table->string('pickup_address')->nullable()->after('longitude');
            }
            
            if (!Schema::hasColumn('orders', 'pickup_latitude')) {
                $table->decimal('pickup_latitude', 10, 8)->nullable()->after('pickup_address');
            }
            
            if (!Schema::hasColumn('orders', 'pickup_longitude')) {
                $table->decimal('pickup_longitude', 11, 8)->nullable()->after('pickup_latitude');
            }
            
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->enum('order_type', ['regular', 'custom_order'])->default('regular')->after('pickup_longitude');
            }
        });

        // Add fields to order_items table ONLY if they don't exist
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'custom_name')) {
                $table->string('custom_name')->nullable()->after('product_id');
            }
            
            if (!Schema::hasColumn('order_items', 'type')) {
                $table->enum('type', ['product', 'custom'])->default('product')->after('custom_name');
            }
        });

        // Make product_id nullable if it's not already
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->change();
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = ['pickup_address', 'pickup_latitude', 'pickup_longitude', 'order_type'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $columns = ['custom_name', 'type'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};