<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Do nothing — columns already exist
        \Log::info('Migration skipped: columns already exist in orders table');
    }

    public function down()
    {
        // Optionally remove columns if you ever roll back
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'picked_up_at', 'delivered_at']);
        });
    }
};