<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARstampRally202611: 応募資格・当選倍率の正本を「1 端末（fingerprint）単位の収集種数」へ切り替える（FR-56）。
 *
 * 既存の stamp_count / tier / weight は「session_id OR fingerprint の合算値」として非破壊で残す
 * （監査証跡・合算表示用）。抽選と応募可否は device_* 列を正本とする。
 *
 * 既存行の device_* は NULL のまま（grandfathering）。NULL の行は weight をそのまま使うため、
 * 合算基準で 2 倍 / 3 倍を取得済みの応募者に遡及して倍率を下げない（FR-57）。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('device_stamp_count')->nullable()->after('stamp_count');
            $table->unsignedTinyInteger('device_tier')->nullable()->after('device_stamp_count');
            $table->unsignedTinyInteger('device_weight')->nullable()->after('device_tier');
        });

        Schema::table('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->index(['device_weight', 'applied_at'], 'stamp202611_voucher_device_weight_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->dropIndex('stamp202611_voucher_device_weight_idx');
            $table->dropColumn(['device_stamp_count', 'device_tier', 'device_weight']);
        });
    }
};
