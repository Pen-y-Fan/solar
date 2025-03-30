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
        Schema::create('inverters', static function (Blueprint $table) {
            $table->id();
            // Number
            // Time
            $table->timestamp('period')->nullable()->unique()->comment('30 min period');
            // Working State
            // Alarm Code
            // DC Voltage PvPV1(V)
            // DC Current PvPV1(A)
            // DC Power PvPV1(W)
            // DC Voltage PvPV2(V)
            // DC Current PvPV2(A)
            // DC Power PvPV2(W)
            // DC Voltage PvMPPT1(V)
            // DC Current PvMPPT1(A)
            // DC Power PvMPPT1(W)
            // DC Voltage PvMPPT2(V)
            // DC Current PvMPPT2(A)
            // DC Power PvMPPT2(W)
            // AC Voltage U(V)
            // AC Current U(A)
            // Total Inverter Power(W)
            // AC Frequency(Hz)
            // Today Yield(kWh)
            $table->float('yield')->nullable()->comment('PV yield for the 30 min period');
            // Month Yield(kWh)
            // Annual Yield(kWh)
            // Total Yield(kWh)
            // Inverter Internal Operating Ambient Temperature(℃)
            // Inverter Ambient Temperature(℃)
            // Dispersion Rate
            // Initialize the grounding voltage(V)
            // DC bus voltage(V)
            // Dc bus half voltage(V)
            // Insulation Resistance Realtime Value
            // Grid Voltage U(V)
            // Grid Voltage V(V)
            // Grid Voltage W(V)
            // Grid Current U(A)
            // Grid Current V(A)
            // Grid Current W(A)
            // Grid Total Active Power(W)
            // Grid Total Reactive Power(Var)
            // Grid Total Apparent Power(VA)
            // Grid Frequency(Hz)
            // Grid Power Factor(%)
            // Daily Energy to Grid(kWh)
            $table->float('to_grid')->nullable()->comment('Excess energy send to the grid for the 30 min period');
            // Total Energy to Grid(kWh)
            // Daily Energy from Grid(kWh)
            $table->float('from_grid')->nullable()->comment('Import energy send from the grid for the 30 min period');
            // Total Energy from Grid(kWh)
            // Battery Voltage(V)
            // Battery Current(A)
            // Battery Power(W)
            // Today Energy to Battery(kWh)
            // Total Energy to Battery(kWh)
            // Today Energy from Battery(kWh)
            // Total Energy from Battery(kWh)
            // BMS Battery Voltage(V)
            // BMS Battery Current(A)
            // Battery SOC(%)
            // Battery SOH(%)
            // BMS Battery Charge Current Limit(A)
            // BMS Battery Discharge Current Limit(A)
            // Battery Charging&Discharging Direction Settings
            // Battery Charging&Discharging Current Settings
            // Max. Battery Charging Current Settings(A)
            // Max. Battery Discharging Current Settings(A)
            // Battery UnderVoltage Protection Settings(V)
            // Battery Float Voltage Settings(V)
            // Battery Equalization Voltage Settings(V)
            // Battery Over Voltage Protection Settings(V)
            // Battery2 Charging&Discharging Direction Settings
            // Battery2 Charging&Discharging Current Settings
            // Max. Battery2 Charging Current Settings(A)
            // Max. Battery2 Discharging Current Settings(A)
            // Battery2 Under Voltage Protection Settings(V)
            // Battery2 Float Voltage Settings(V)
            // Battery2 Equalization Voltage Settings(V)
            // Battery2 Over Voltage Protection Settings(V)
            // Backup AC Voltage(V)
            // Backup AC Current(A)
            // Backup Load Power(W)
            // Total Consume Energy Power(W)
            // Daily Consumption Energy(kWh)
            $table->float('consumption')->nullable()->comment('Energy consumed during the 30 min period');
            // Total Consumption Energy(kWh)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inverters');
    }
};
