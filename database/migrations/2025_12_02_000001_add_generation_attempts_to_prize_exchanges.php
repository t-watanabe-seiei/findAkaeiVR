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
        Schema::table('prize_exchanges', function (Blueprint $table) {
            $table->integer('generation_attempts')->default(0)->after('prize_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prize_exchanges', function (Blueprint $table) {
            $table->dropColumn('generation_attempts');
        });
    }
};
