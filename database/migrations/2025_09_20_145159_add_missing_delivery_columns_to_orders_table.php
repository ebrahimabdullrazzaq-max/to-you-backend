<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Check and add only missing columns
            if (!Schema::hasColumn('orders', 'delivery_current_lat')) {
                $table->decimal('delivery_current_lat', 10, 8)->nullable();
            }
            
            if (!Schema::hasColumn('orders', 'delivery_current_lng')) {
                $table->decimal('delivery_current_lng', 11, 8)->nullable();
            }
            
            if (!Schema::hasColumn('orders', 'delivery_updated_at')) {
                $table->timestamp('delivery_updated_at')->nullable();
            }
            
            if (!Schema::hasColumn('orders', 'preparing_at')) {
                $table->timestamp('preparing_at')->nullable();
            }
            
            if (!Schema::hasColumn('orders', 'on_the_way_at')) {
                $table->timestamp('on_the_way_at')->nullable();
            }
            
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
            
            if (!Schema::hasColumn('orders', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove the columns if they exist
            if (Schema::hasColumn('orders', 'delivery_current_lat')) {
                $table->dropColumn('delivery_current_lat');
            }
            
            if (Schema::hasColumn('orders', 'delivery_current_lng')) {
                $table->dropColumn('delivery_current_lng');
            }
            
            if (Schema::hasColumn('orders', 'delivery_updated_at')) {
                $table->dropColumn('delivery_updated_at');
            }
            
            if (Schema::hasColumn('orders', 'preparing_at')) {
                $table->dropColumn('preparing_at');
            }
            
            if (Schema::hasColumn('orders', 'on_the_way_at')) {
                $table->dropColumn('on_the_way_at');
            }
            
            if (Schema::hasColumn('orders', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            
            if (Schema::hasColumn('orders', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });
    }
};