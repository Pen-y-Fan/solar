<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy;

use App\Domain\Energy\Actions\AgileImport;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgileImportActionTest extends TestCase
{
    use RefreshDatabase;

    public function testExecuteReturnsActionResult(): void
    {
        $now = now()->startOfHour();

        Http::fake([
            'https://api.octopus.energy/*standard-unit-rates/*' => Http::response([
                'results' => [
                    [
                        'value_exc_vat' => 10.0,
                        'value_inc_vat' => 12.0,
                        'valid_from' => $now->copy()->toIso8601String(),
                        'valid_to' => $now->copy()->addMinutes(30)->toIso8601String(),
                        'payment_method' => null,
                    ],
                ],
            ], 200),
        ]);

        $result = (new AgileImport())->execute();

        $this->assertInstanceOf(ActionResult::class, $result);
    }
}
