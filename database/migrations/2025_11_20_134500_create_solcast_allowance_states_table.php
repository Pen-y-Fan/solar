<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solcast_allowance_states', function (Blueprint $table): void {
            $table->id();
            $table->string('day_key', 8); // YYYYMMDD in SOLCAST_RESET_TZ
            $table->unsignedInteger('count')->default(0);

            $table->timestamp('last_attempt_at_forecast')->nullable();
            $table->timestamp('last_attempt_at_actual')->nullable();
            $table->timestamp('last_success_at_forecast')->nullable();
            $table->timestamp('last_success_at_actual')->nullable();

            $table->timestamp('backoff_until')->nullable();
            $table->timestamp('reset_at');

            $table->timestamps();

            $table->unique('day_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solcast_allowance_states');
    }
};
