<?php

namespace Tests\Feature\Models;

use App\Domain\Energy\Models\AgileImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgileImportTest extends TestCase
{
    use RefreshDatabase;

    public function testAnAgileImportCanBeCreated(): void
    {
        $valueExcVat = fake()->randomFloat(4);
        $valueIncVat = fake()->randomFloat(4);

        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => $valueExcVat,
            'value_inc_vat' => $valueIncVat,
        ];
        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertDatabaseCount(AgileImport::class, 1);
        $this->assertSame($data['valid_from']->toDateTimeString(), $agileImport->valid_from->toDateTimeString());
        $this->assertSame($data['valid_to']->toDateTimeString(), $agileImport->valid_to->toDateTimeString());
        $this->assertSame($data['value_exc_vat'], $agileImport->value_exc_vat);
        $this->assertSame($data['value_inc_vat'], $agileImport->value_inc_vat);
    }

    public function testAAgileImportCanBeCreatedWithUtcIso8601DateString(): void
    {
        $valueIncVat = fake()->randomFloat(4);
        $valueExcVat = fake()->randomFloat(4);

        $data = [
            'valid_from' => now('UTC')->parse('2024-06-15T09:00:00.0000000Z')->toDateTimeString(),
            'valid_to' => now('UTC')->parse('2024-06-15T09:00:30.0000000Z')->toDateTimeString(),
            'value_exc_vat' => $valueIncVat,
            'value_inc_vat' => $valueExcVat,
        ];
        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertDatabaseCount(AgileImport::class, 1);
        $parsedValidFrom = now()->parse($data['valid_from'])->toDateTimeString();
        $this->assertSame($parsedValidFrom, $agileImport->valid_from->toDateTimeString());
        $parsedValidTo = now()->parse($data['valid_to'])->toDateTimeString();
        $this->assertSame($parsedValidTo, $agileImport->valid_to->toDateTimeString());
        $this->assertSame($data['value_exc_vat'], $agileImport->value_exc_vat);
        $this->assertSame($data['value_inc_vat'], $agileImport->value_inc_vat);
    }

    public function testAnAgileImportCanNotBeCreatedForTheSamePeriod(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ];
        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);

        $newData = [
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'],
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ];

        // Will throw an exception
        AgileImport::create($newData);
    }

    public function testAnAgileImportCanBeUpsertedForTheSamePeriod(): void
    {
        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => fake()->randomFloat(4),
            'value_inc_vat' => fake()->randomFloat(4),
        ];

        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertSame($data['value_exc_vat'], $agileImport->value_exc_vat);

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
                'valid_from' => $data['valid_from']->clone()->addMinutes(30)
                    ->timezone('UTC')->toDateTimeString(),
                'valid_to' => $data['valid_to']->clone()->addMinutes(30)
                    ->timezone('UTC')->toDateTimeString(),
                'value_exc_vat' => $valueExcVat2,
                'value_inc_vat' => $valueIncVat2,
            ],
        ];

        $additionalAgileImport = AgileImport::upsert(
            $newData,
            uniqueBy: ['valid_from'],
            update: ['value_exc_vat', 'value_inc_vat'],
        );

        $this->assertDatabaseCount(AgileImport::class, 2);

        $this->assertSame($additionalAgileImport, 2);

        $agileImport->refresh();
        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertSame($newData[0]['value_exc_vat'], $agileImport->value_exc_vat);
        $this->assertSame($newData[0]['value_inc_vat'], $agileImport->value_inc_vat);
        $this->assertNotSame($data['value_exc_vat'], $agileImport->value_exc_vat);
        $this->assertNotSame($data['value_inc_vat'], $agileImport->value_inc_vat);

        $newAgileImport = AgileImport::whereValidFrom($newData[1]['valid_from'])->first();

        $this->assertInstanceOf(AgileImport::class, $newAgileImport);
        $this->assertSame($newData[1]['value_exc_vat'], $newAgileImport->value_exc_vat);
        $this->assertSame($newData[1]['value_inc_vat'], $newAgileImport->value_inc_vat);
    }
}
