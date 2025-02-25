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
        Schema::create('strategies', function (Blueprint $table) {
            $table->id();

            $table->timestamp('period')
                ->nullable()
                ->unique()
                ->comment('30 min period');

            $table->integer('battery_percentage')
                ->nullable()
                ->comment('Battery state of charge in percentage (0-100)');

            $table->boolean('strategy_manual')
                ->nullable()
                ->comment('Manual strategy');

            $table->boolean('strategy1')
                ->nullable()
                ->comment('Average strategy based on an low average cost');

            $table->boolean('strategy2')
                ->nullable()
                ->comment('Average strategy based on a lower average cost');

            $table->float('consumption_last_week')
                ->nullable()
                ->comment('Copy of consumption based on 1 week ago');

            $table->float('consumption_average')
                ->nullable()
                ->comment('Copy of consumption based on an average');

            $table->float('consumption_manual')
                ->nullable()
                ->comment('Manually adjusted consumption figure');

            $table->float('import_value_inc_vat')
                ->nullable()
                ->comment('Import value including VAT (copy)');

            $table->float('export_value_inc_vat')
                ->nullable()
                ->comment('Export value including VAT (copy)');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategies');
    }
};
