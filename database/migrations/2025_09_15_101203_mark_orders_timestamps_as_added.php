<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Do nothing — columns already added manually
        \Log::info('Manual migration: Orders timestamps already added');
    }

    public function down()
    {
        // Optional: remove if needed later
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'picked_up_at']);
        });
    }
};