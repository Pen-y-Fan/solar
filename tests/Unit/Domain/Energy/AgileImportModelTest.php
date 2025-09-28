<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy;

use App\Domain\Energy\Models\AgileImport;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class AgileImportModelTest extends TestCase
{
    public function testMonetaryValueVoMappingRoundTrip(): void
    {
        $model = new AgileImport();

        // Seed raw attributes to simulate DB
        $model->setRawAttributes([
            'value_exc_vat' => 0.1234,
            'value_inc_vat' => 0.1481,
        ], true);

        // Accessors read via VO
        $this->assertEqualsWithDelta(0.1234, $model->value_exc_vat, 1e-6);
        $this->assertEqualsWithDelta(0.1481, $model->value_inc_vat, 1e-6);

        // Mutate using accessors; ensure VO updates and attributes reflect
        $model->value_exc_vat = 0.2000;
        $model->value_inc_vat = 0.2400;

        $this->assertEqualsWithDelta(0.2000, $model->value_exc_vat, 1e-6);
        $this->assertEqualsWithDelta(0.2400, $model->value_inc_vat, 1e-6);
    }

    public function testTimeIntervalVoMappingRoundTrip(): void
    {
        $model = new AgileImport();

        $from = CarbonImmutable::parse('2025-09-28 10:00:00', 'UTC');
        $to   = CarbonImmutable::parse('2025-09-28 10:30:00', 'UTC');

        // Seed raw attributes
        $model->setRawAttributes([
            'valid_from' => $from->toIso8601String(),
            'valid_to' => $to->toIso8601String(),
        ], true);

        // Accessors read via VO and preserve immutability + timezone
        $this->assertInstanceOf(CarbonImmutable::class, $model->valid_from);
        $this->assertInstanceOf(CarbonImmutable::class, $model->valid_to);
        $this->assertSame($from->toIso8601String(), $model->valid_from->toIso8601String());
        $this->assertSame($to->toIso8601String(), $model->valid_to->toIso8601String());

        // Mutate via accessors with CarbonImmutable
        $newFrom = $from->addMinutes(30);
        $newTo = $to->addMinutes(30);
        // Set valid_to first to avoid transient invalid interval (equal start/end)
        $model->valid_to = $newTo;
        $model->valid_from = $newFrom;

        $this->assertSame($newFrom->toIso8601String(), $model->valid_from->toIso8601String());
        $this->assertSame($newTo->toIso8601String(), $model->valid_to->toIso8601String());
    }
}
