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
        Schema::table('inverters', function (Blueprint $table) {
            $table->unsignedTinyInteger('battery_soc')
                ->nullable()
                ->after('from_grid')
                ->comment('Battery state of charge in percentage (0-100)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inverters', function (Blueprint $table) {
            $table->dropColumn('battery_soc');
        });
    }
};
