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
        Schema::table('shooting_scores', function (Blueprint $table) {
            if (!Schema::hasColumn('shooting_scores', 'max_combo')) {
                $table->integer('max_combo')->default(0)->after('game_mode'); // 最大コンボ数
            }
            if (!Schema::hasColumn('shooting_scores', 'enemies_defeated')) {
                $table->integer('enemies_defeated')->default(0)->after('max_combo'); // 倒した敵の数
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shooting_scores', function (Blueprint $table) {
            $table->dropColumn(['max_combo', 'enemies_defeated']);
        });
    }
};
