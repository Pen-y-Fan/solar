<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('strategies', function (Blueprint $table) {

            $driver = config('database.default');

            if ($driver === 'sqlite') {
                // Add the SQLite trigger to calculate the column
                DB::unprepared('
                CREATE TRIGGER calculate_value_trigger
                AFTER INSERT ON strategies
                FOR EACH ROW
                BEGIN
                    UPDATE strategies
                    SET
                        consumption_average_cost = NEW.consumption_average * NEW.import_value_inc_vat,
                        consumption_last_week_cost = NEW.consumption_last_week * NEW.import_value_inc_vat
                    WHERE id = NEW.id;
                END;
            ');
            } else {
                echo 'This migration is only supported for SQLite';
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS calculate_value_trigger;');
        } else {
            echo 'This migration is only supported for SQLite';
        }

    }
};
