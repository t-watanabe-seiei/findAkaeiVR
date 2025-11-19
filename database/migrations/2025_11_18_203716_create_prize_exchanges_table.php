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
        Schema::create('prize_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('fingerprint')->nullable()->unique();
            $table->string('prize_code', 10)->unique();
            $table->string('user_agent');
            $table->string('ip_address');
            $table->json('device_info');
            $table->json('stamps_data');
            $table->timestamp('exchanged_at');
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prize_exchanges');
    }
};
