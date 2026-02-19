<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marker_scans', function (Blueprint $table) {
            // capture_typeカラムを追加（デフォルト: 'ball_hit'）
            $table->string('capture_type', 20)->default('ball_hit')->after('marker_name');
            
            // インデックスを追加（検索パフォーマンス向上）
            $table->index('capture_type');
            $table->index(['marker_id', 'capture_type']);
            $table->index(['fingerprint', 'marker_id', 'capture_type']);
        });
        
        // 既存レコードのcapture_typeを'ball_hit'に設定（念のため）
        DB::table('marker_scans')
            ->whereNull('capture_type')
            ->orWhere('capture_type', '')
            ->update(['capture_type' => 'ball_hit']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marker_scans', function (Blueprint $table) {
            // インデックスを削除
            $table->dropIndex(['fingerprint', 'marker_id', 'capture_type']);
            $table->dropIndex(['marker_id', 'capture_type']);
            $table->dropIndex(['capture_type']);
            
            // capture_typeカラムを削除
            $table->dropColumn('capture_type');
        });
    }
};
