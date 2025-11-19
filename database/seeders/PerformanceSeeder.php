<?php

namespace Database\Seeders;

use App\Domain\Energy\Models\AgileExport;
use App\Domain\Energy\Models\AgileImport;
use App\Domain\Energy\Models\Inverter;
use App\Domain\Forecasting\Models\Forecast;
use App\Domain\Strategy\Models\Strategy;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class PerformanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure a known test user exists for local/dev usage
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                // Use default password if model casts exist; otherwise store plain and let framework hash on auth
                'password' => bcrypt('password'),
            ]
        );

        // Dataset size toggle via env: small|medium|large (default: small)
        $size = strtolower((string) (env('PERF_DATASET_SIZE') ?: 'small'));
        if (! in_array($size, ['small', 'medium', 'large'], true)) {
            $size = 'small';
        }

        // Determine time ranges
        $now = CarbonImmutable::now()->startOfHour();
        [$hours, $halfHours] = match ($size) {
            'small' => [24, 48],      // 1 day hourly; 1 day half-hourly
            'medium' => [24 * 7, 48 * 7], // 7 days
            'large' => [24 * 30, 48 * 30], // 30 days
            default => [24, 48],
        };

        $this->command?->info("Seeding performance dataset: {$size} (")
            && $this->command?->info("hours={$hours}, halfHours={$halfHours}");

        // Seed Strategies (hourly)
        $this->seedStrategies($now, $hours);

        // Seed Forecasts (hourly, using period_end)
        $this->seedForecasts($now, $hours);

        // Seed Inverter samples (hourly)
        $this->seedInverter($now, $hours);

        // Seed Agile Import/Export rates (half-hourly)
        $this->seedAgileRates($now, $halfHours);
    }

    private function seedStrategies(CarbonImmutable $start, int $hours): void
    {
        // Use factory if available for realistic distributions
        for ($i = 0; $i < $hours; $i++) {
            $period = $start->subHours($hours - $i);

            Strategy::query()->updateOrCreate(
                ['period' => $period],
                [
                    'battery_charge_amount' => random_int(0, 500) / 10,
                    'import_amount' => random_int(0, 500) / 10,
                    'export_amount' => random_int(0, 500) / 10,
                    'battery_percentage_manual' => random_int(0, 100),
                    'strategy_manual' => (bool) random_int(0, 1),
                    'strategy1' => (bool) random_int(0, 1),
                    'strategy2' => (bool) random_int(0, 1),
                    'consumption_last_week' => random_int(0, 500) / 10,
                    'consumption_average' => random_int(0, 500) / 10,
                    'consumption_manual' => random_int(0, 500) / 10,
                    'import_value_inc_vat' => random_int(0, 999) / 100,
                    'export_value_inc_vat' => random_int(0, 999) / 100,
                ]
            );
        }
    }

    private function seedForecasts(CarbonImmutable $start, int $hours): void
    {
        for ($i = 0; $i < $hours; $i++) {
            $periodEnd = $start->subHours($hours - $i);

            Forecast::query()->updateOrCreate(
                ['period_end' => $periodEnd],
                [
                    'pv_estimate' => random_int(0, 500) / 10,
                    'pv_estimate10' => random_int(0, 500) / 10,
                    'pv_estimate90' => random_int(0, 500) / 10,
                ]
            );
        }
    }

    private function seedInverter(CarbonImmutable $start, int $hours): void
    {
        $batterySoc = random_int(20, 90);
        for ($i = 0; $i < $hours; $i++) {
            $period = $start->subHours($hours - $i);

            $yield = max(0, random_int(0, 300) / 10);
            $toGrid = max(0, random_int(0, 200) / 10);
            $fromGrid = max(0, random_int(0, 200) / 10);
            $consumption = max(0, $yield + $fromGrid - $toGrid + random_int(0, 50) / 10);
            $batterySoc = max(0, min(100, $batterySoc + random_int(-3, 3)));

            Inverter::query()->updateOrCreate(
                ['period' => $period],
                [
                    'yield' => $yield,
                    'to_grid' => $toGrid,
                    'from_grid' => $fromGrid,
                    'consumption' => $consumption,
                    'battery_soc' => $batterySoc,
                ]
            );
        }
    }

    private function seedAgileRates(CarbonImmutable $start, int $halfHours): void
    {
        // Start from the latest half-hour boundary
        $t = $start->subMinutes($start->minute % 30)->subSeconds($start->second);

        for ($i = 0; $i < $halfHours; $i++) {
            $from = $t->subMinutes(($halfHours - $i) * 30);
            $to = $from->addMinutes(30);

            // Use a simple sinusoidal pattern for day-night variation
            $hourOfDay = (int) $from->format('G');
            $base = 10 + 5 * sin(deg2rad(($hourOfDay / 24) * 360));
            $importExc = round($base + random_int(-100, 100) / 100, 2);
            $importInc = round($importExc * 1.2, 2);
            $exportExc = round(max(0.0, $importExc - 2.0 + random_int(-50, 50) / 100), 2);
            $exportInc = round($exportExc * 1.2, 2);

            AgileImport::query()->updateOrCreate(
                ['valid_from' => $from],
                [
                    'valid_to' => $to,
                    'value_exc_vat' => $importExc,
                    'value_inc_vat' => $importInc,
                ]
            );

            AgileExport::query()->updateOrCreate(
                ['valid_from' => $from],
                [
                    'valid_to' => $to,
                    'value_exc_vat' => $exportExc,
                    'value_inc_vat' => $exportInc,
                ]
            );
        }
    }
}
