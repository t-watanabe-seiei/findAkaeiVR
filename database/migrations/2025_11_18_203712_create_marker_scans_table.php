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
        Schema::create('marker_scans', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('fingerprint')->nullable()->index();
            $table->string('marker_id')->index();
            $table->string('marker_name');
            $table->integer('scan_count')->default(1);
            $table->timestamp('scanned_at');
            $table->string('user_agent');
            $table->string('ip_address');
            $table->json('device_info');
            $table->timestamps();
            
            // 複合インデックス
            $table->index(['session_id', 'marker_id']);
            $table->index(['fingerprint', 'marker_id']);
            $table->index('scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marker_scans');
    }
};
