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
        Schema::create('agile_exports', static function (Blueprint $table) {
            $table->id();
            $table->float('value_exc_vat')->nullable();
            $table->float('value_inc_vat')->nullable();
            $table->timestamp('valid_from')->nullable()->unique();
            $table->timestamp('valid_to')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agile_exports');
    }
};
