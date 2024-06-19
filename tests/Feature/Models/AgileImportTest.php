<?php

namespace Tests\Feature\Models;

use App\Models\AgileImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgileImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_octopus_import_can_be_created(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => $estimate,
            'value_inc_vat' => $estimate*1.05,
        ];
        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertDatabaseCount(AgileImport::class, 1);
        $this->assertSame($data['valid_from']->toDateTimeString(), $agileImport->valid_from->toDateTimeString());
        $this->assertSame($data['valid_to']->toDateTimeString(), $agileImport->valid_to->toDateTimeString());
        $this->assertSame($data['value_exc_vat'], $agileImport->value_exc_vat);
        $this->assertSame($data['value_inc_vat'], $agileImport->value_inc_vat);
    }

    public function test_a_agileImport_can_be_created_with_utc_iso_8601_date_string(): void
    {
        $estimateIncVat = fake()->randomFloat(4);
        $estimateExcVat = fake()->randomFloat(4);

        $data = [
            "valid_from" => now('UTC')->parse("2024-06-15T09:00:00.0000000Z")->toDateTimeString(),
            "valid_to" => now('UTC')->parse("2024-06-15T09:00:30.0000000Z")->toDateTimeString(),
            'value_exc_vat' => $estimateIncVat,
            'value_inc_vat' => $estimateExcVat,
        ];
        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertDatabaseCount(AgileImport::class, 1);
        $this->assertSame(now()->parse($data['valid_from'])->toDateTimeString(), $agileImport->valid_from->toDateTimeString());
        $this->assertSame(now()->parse($data['valid_to'])->toDateTimeString(), $agileImport->valid_to->toDateTimeString());
        $this->assertSame($data['value_exc_vat'], $agileImport->value_exc_vat);
        $this->assertSame($data['value_inc_vat'], $agileImport->value_inc_vat);
    }

    public function test_an_octopus_import_can_not_be_created_for_the_same_period(): void
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

    public function test_an_octopus_import_can_be_upserted_for_the_same_period(): void
    {
        $estimate = fake()->randomFloat(4);
        $data = [
            'valid_from' => now()->addHours(2)->startOfHour(),
            'valid_to' => now()->addHours(2)->startOfHour()->addMinutes(30),
            'value_exc_vat' => $estimate,
            'value_inc_vat' => $estimate,
        ];


        $agileImport = AgileImport::create($data);

        $this->assertInstanceOf(AgileImport::class, $agileImport);
        $this->assertSame($data['value_exc_vat'], $agileImport->value_exc_vat);

        $estimateExcVat1 = 2.22;
        $estimateIncVat1 = 2.44;
        $estimateExcVat2 = 3.22;
        $estimateIncVat2 = 3.55;

        $newData = [
            [
                'valid_from' => $data['valid_from']->timezone('UTC')->toDateTimeString(),
                'valid_to' => $data['valid_to']->timezone('UTC')->toDateTimeString(),
                'value_exc_vat' => $estimateExcVat1,
                'value_inc_vat' => $estimateIncVat1,
            ],
            [
                'valid_from' => $data['valid_from']->clone()->addMinutes(30)->timezone('UTC')->toDateTimeString(),
                'valid_to' => $data['valid_to']->clone()->addMinutes(30)->timezone('UTC')->toDateTimeString(),
                'value_exc_vat' => $estimateExcVat2,
                'value_inc_vat' => $estimateIncVat2,
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
