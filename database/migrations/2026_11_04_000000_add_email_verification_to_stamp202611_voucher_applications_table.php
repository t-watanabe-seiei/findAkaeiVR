<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARstampRally202611: 市内共通商品券の抽選応募におけるメールアドレス確認（requirements.md FR-61 / design.md §5.7）。
 *
 * 確認コード（6 桁）はサーバー側の Cache（TTL 付き）にのみ保持し、DB には保存しない。
 * DB には「コード送信日時」と「確認完了日時」だけを残し、個人情報が増えないようにする。
 *
 * grandfathering（FR-61）: このマイグレーション時点で存在する応募行は確認済みとして扱う
 * （email_verified_at = applied_at）。確認導入前に応募した利用者へ再確認を要求しない。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->timestamp('email_code_sent_at')->nullable()->after('device_info');
            $table->timestamp('email_verified_at')->nullable()->after('email_code_sent_at');
        });

        // 確認導入以前の応募行は「応募時点で確認済み」として扱う（FR-61）
        DB::table('stamp202611_voucher_applications')
            ->whereNull('email_verified_at')
            ->whereNotNull('applied_at')
            ->update(['email_verified_at' => DB::raw('applied_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->dropColumn(['email_code_sent_at', 'email_verified_at']);
        });
    }
};
