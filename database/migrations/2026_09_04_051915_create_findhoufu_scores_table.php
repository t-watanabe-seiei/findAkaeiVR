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
        Schema::create('findhoufu_scores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->float('score')->default(0);
            $table->integer('max_combo')->default(0);
            $table->integer('hits')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findhoufu_scores');
    }
};
