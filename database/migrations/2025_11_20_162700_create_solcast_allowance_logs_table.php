<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('solcast_allowance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('endpoint')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedInteger('status')->nullable();
            $table->timestamp('backoff_until')->nullable();
            $table->string('day_key')->nullable();
            $table->timestamp('reset_at')->nullable();
            $table->timestamp('next_eligible_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['event_type']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solcast_allowance_logs');
    }
};
