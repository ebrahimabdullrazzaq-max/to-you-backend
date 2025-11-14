<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_current_lat', 10, 8)->nullable()->after('longitude');
            $table->decimal('delivery_current_lng', 11, 8)->nullable()->after('delivery_current_lat');
            $table->timestamp('delivery_updated_at')->nullable()->after('delivery_current_lng');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_current_lat', 'delivery_current_lng', 'delivery_updated_at']);
        });
    }
};