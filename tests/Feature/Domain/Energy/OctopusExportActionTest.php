<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Energy;

use App\Domain\Energy\Actions\OctopusExport as OctopusExportAction;
use App\Domain\Energy\Models\OctopusExport;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OctopusExportActionTest extends TestCase
{
    use RefreshDatabase;

    public function testExecutePersistsExportWithUtcIntervalsAndPrecision(): void
    {
        // Provide required config values expected by the action
        Config::set('octopus.api_key', 'test_api_key');
        Config::set('octopus.export_mpan', 'mpan999');
        Config::set('octopus.export_serial_number', 'serialXYZ');

        $start = now('Europe/London')->startOfHour();

        Http::fake([
            'https://api.octopus.energy/*/consumption*' => Http::response([
                'results' => [
                    [
                        'consumption' => 0.002,
                        'interval_start' => $start->copy()->toIso8601String(),
                        'interval_end' => $start->copy()->addMinutes(30)->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        $action = app(OctopusExportAction::class);
        $result = $action->execute();

        $this->assertInstanceOf(ActionResult::class, $result);
        $this->assertTrue($result->isSuccess(), $result->getMessage());

        $row = OctopusExport::query()->firstOrFail();
        $this->assertEqualsWithDelta(0.002, $row->consumption, 1e-9);
        // Interval converted to UTC and 30 minutes apart
        $expectedStart = $start->clone()->timezone('UTC')->toIso8601String();
        $expectedEnd = $start->clone()->addMinutes(30)->timezone('UTC')->toIso8601String();
        $this->assertSame($expectedStart, $row->interval_start->timezone('UTC')->toIso8601String());
        $this->assertSame($expectedEnd, $row->interval_end->timezone('UTC')->toIso8601String());
        $this->assertEquals(30, $row->interval_start->diffInMinutes($row->interval_end));
    }
}
