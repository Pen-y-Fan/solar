<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Energy;

use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Energy\ImportAgileRatesCommand;
use App\Domain\Energy\Models\AgileImport;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgileImportActionTest extends TestCase
{
    use RefreshDatabase;

    public function testExecutePersistsRatesWithUtcIntervalAndPrecision(): void
    {
        $now = now()->startOfHour();

        Http::fake([
            'https://api.octopus.energy/*standard-unit-rates/*' => Http::response([
                'results' => [
                    [
                        'value_exc_vat' => 10.1234,
                        'value_inc_vat' => 12.5678,
                        'valid_from' => $now->copy()->timezone('Europe/London')->toIso8601String(),
                        'valid_to' => $now->copy()->addMinutes(30)->timezone('Europe/London')->toIso8601String(),
                        'payment_method' => null,
                    ],
                ],
            ], 200),
        ]);

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);
        $result = $bus->dispatch(new ImportAgileRatesCommand());

        $this->assertInstanceOf(ActionResult::class, $result);
        $this->assertTrue($result->isSuccess(), $result->getMessage());

        $row = AgileImport::query()->firstOrFail();
        // Monetary precision preserved
        $this->assertEqualsWithDelta(10.1234, $row->value_exc_vat, 1e-6);
        $this->assertEqualsWithDelta(12.5678, $row->value_inc_vat, 1e-6);
        // Interval converted to UTC and 30 minutes apart
        $expectedFrom = $now->clone()->timezone('UTC')->toIso8601String();
        $expectedTo = $now->clone()->addMinutes(30)->timezone('UTC')->toIso8601String();
        $this->assertSame($expectedFrom, $row->valid_from->timezone('UTC')->toIso8601String());
        $this->assertSame($expectedTo, $row->valid_to->timezone('UTC')->toIso8601String());
        $this->assertEquals(30, $row->valid_from->diffInMinutes($row->valid_to));

        // VO-backed accessors should be consistent
        $mv = $row->getMonetaryValueObject();
        $ti = $row->getTimeIntervalObject();
        $this->assertEqualsWithDelta($row->value_exc_vat, $mv->excVat, 1e-9);
        $this->assertEqualsWithDelta($row->value_inc_vat, $mv->incVat, 1e-9);
        $this->assertSame($row->valid_from->toIso8601String(), $ti->from->toIso8601String());
        $this->assertSame($row->valid_to->toIso8601String(), $ti->to->toIso8601String());
    }
}
