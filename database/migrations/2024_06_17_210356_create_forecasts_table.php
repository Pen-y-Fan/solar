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
        Schema::create('forecasts', static function (Blueprint $table) {
            $table->id();
            $table->timestamp('period_end')->nullable()->unique();
            $table->float('pv_estimate')->nullable();
            $table->float('pv_estimate10')->nullable();
            $table->float('pv_estimate90')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};
