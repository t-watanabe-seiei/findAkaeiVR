<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARstampRally202611 市内共通商品券の抽選応募（design.md §5.4）。
 *
 * 202611 専用テーブル。既存の marker_scans / prize_exchanges には一切触れない（AC-7 / AC-11）。
 * 個人情報（email・name）はこのテーブルにのみ保存する。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('fingerprint', 64)->unique();   // 1 fingerprint = 1 応募
            $table->string('email', 190)->unique();        // 1 メールアドレス = 1 応募（FR-44）
            $table->string('name', 100);
            $table->unsignedSmallInteger('stamp_count');    // 応募／最終更新時点の収集種数
            $table->unsignedTinyInteger('tier');            // 10 / 15 / 20
            $table->unsignedTinyInteger('weight');          // 1 / 2 / 3（当選倍率）
            $table->string('user_agent');
            $table->string('ip_address');
            $table->json('device_info');
            $table->timestamp('applied_at');
            $table->timestamps();
            $table->index(['weight', 'applied_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp202611_voucher_applications');
    }
};
