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
        Schema::create('octopus_exports', static function (Blueprint $table) {
            $table->id();
            $table->float('consumption')->nullable();
            $table->timestamp('interval_start')->nullable()->unique();
            $table->timestamp('interval_end')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('octopus_exports');
    }
};
