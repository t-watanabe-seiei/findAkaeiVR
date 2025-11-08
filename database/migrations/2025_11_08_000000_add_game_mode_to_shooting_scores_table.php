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
            $table->string('game_mode', 50)->default('terrer')->after('level'); // ゲームモード（terrer, model, insect）
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shooting_scores', function (Blueprint $table) {
            $table->dropColumn('game_mode');
        });
    }
};
