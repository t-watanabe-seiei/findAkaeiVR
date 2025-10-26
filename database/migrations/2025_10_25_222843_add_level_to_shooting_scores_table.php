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
            $table->integer('level')->default(1)->after('score'); // レベル（1 or 2）
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shooting_scores', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
