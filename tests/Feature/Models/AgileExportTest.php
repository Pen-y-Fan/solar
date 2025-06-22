<?php

namespace Tests\Feature\Models;

use App\Domain\Energy\Models\AgileExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgileExportTest extends TestCase
{
    use RefreshDatabase;

    public function testAnAgileExportCanBeCreated(): void
    {
        $valueExcVat = fake()->randomFloat(4);
        $valueIncVat = fake()->randomFloat(4);

        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => $valueExcVat,
            'value_inc_vat' => $valueIncVat,
        ];
        $agileExport = AgileExport::create($data);

        $this->assertInstanceOf(AgileExport::class, $agileExport);
        $this->assertDatabaseCount(AgileExport::class, 1);
        $this->assertSame($data['valid_from']->toDateTimeString(), $agileExport->valid_from->toDateTimeString());
        $this->assertSame($data['valid_to']->toDateTimeString(), $agileExport->valid_to->toDateTimeString());
        $this->assertSame($data['value_exc_vat'], $agileExport->value_exc_vat);
        $this->assertSame($data['value_inc_vat'], $agileExport->value_inc_vat);
    }

    public function testAnAgileExportCanBeCreatedWithUtcIso8601DateString(): void
    {
        $valueIncVat = fake()->randomFloat(4);
        $valueExcVat = fake()->randomFloat(4);

        $data = [
            'valid_from' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z')->toDateTimeString(),
            'valid_to' => now('UTC')->parse('2024-06-15T09:00:30.0000000Z')->toDateTimeString(),
            'value_exc_vat' => $valueIncVat,
            'value_inc_vat' => $valueExcVat,
        ];
        $agileExport = AgileExport::create($data);

        $this->assertInstanceOf(AgileExport::class, $agileExport);
        $this->assertDatabaseCount(AgileExport::class, 1);
        $this->assertSame(
            now()->parse($data['valid_from'])->toDateTimeString(),
            $agileExport->valid_from->toDateTimeString()
        );
        $this->assertSame(
            now()->parse($data['valid_to'])->toDateTimeString(),
            $agileExport->valid_to->toDateTimeString()
        );
        $this->assertSame($data['value_exc_vat'], $agileExport->value_exc_vat);
        $this->assertSame($data['value_inc_vat'], $agileExport->value_inc_vat);
    }

    public function testAnAgileExportCanNotBeCreatedForTheSamePeriod(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ];
        $agileExport = AgileExport::create($data);

        $this->assertInstanceOf(AgileExport::class, $agileExport);

        $newData = [
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'],
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ];

        // Will throw an exception
        AgileExport::create($newData);
    }

    public function testAnAgileExportCanBeUpsertedForTheSamePeriod(): void
    {
        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ];

        $agileExport = AgileExport::create($data);

        $this->assertInstanceOf(AgileExport::class, $agileExport);
        $this->assertSame($data['value_exc_vat'], $agileExport->value_exc_vat);

        $valueExcVat1 = 2.22;
        $valueIncVat1 = 2.44;
        $valueExcVat2 = 3.22;
        $valueIncVat2 = 3.55;

        $newData = [
            [
                'valid_from' => $data['valid_from']->timezone('UTC')->toDateTimeString(),
                'valid_to' => $data['valid_to']->timezone('UTC')->toDateTimeString(),
                'value_exc_vat' => $valueExcVat1,
                'value_inc_vat' => $valueIncVat1,
            ],
            [
                'valid_from' => $data['valid_from']->clone()->addMinutes(30)->timezone('UTC')->toDateTimeString(),
                'valid_to' => $data['valid_to']->clone()->addMinutes(30)->timezone('UTC')->toDateTimeString(),
                'value_exc_vat' => $valueExcVat2,
                'value_inc_vat' => $valueIncVat2,
            ],
        ];

        $additionalAgileExport = AgileExport::upsert(
            $newData,
            uniqueBy: ['valid_from'],
            update: ['value_exc_vat', 'value_inc_vat'],
        );

        $this->assertDatabaseCount(AgileExport::class, 2);

        $this->assertSame($additionalAgileExport, 2);

        $agileExport->refresh();
        $this->assertInstanceOf(AgileExport::class, $agileExport);
        $this->assertSame($newData[0]['value_exc_vat'], $agileExport->value_exc_vat);
        $this->assertSame($newData[0]['value_inc_vat'], $agileExport->value_inc_vat);
        $this->assertNotSame($data['value_exc_vat'], $agileExport->value_exc_vat);
        $this->assertNotSame($data['value_inc_vat'], $agileExport->value_inc_vat);

        $newAgileExport = AgileExport::whereValidFrom($newData[1]['valid_from'])->first();

        $this->assertInstanceOf(AgileExport::class, $newAgileExport);
        $this->assertSame($newData[1]['value_exc_vat'], $newAgileExport->value_exc_vat);
        $this->assertSame($newData[1]['value_inc_vat'], $newAgileExport->value_inc_vat);
    }
}
