<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\Inverter as InverterCommand;
use App\Domain\Energy\Imports\InverterImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ReaderType;
use Mockery;
use Tests\TestCase;

class InverterTest extends TestCase
{
    use RefreshDatabase;

    public function testInverterCommandProcessesXlsAndSkipsNonXls(): void
    {
        Storage::fake();

        // Arrange: one valid .xls and one invalid file
        Storage::put('uploads/valid.xls', 'dummy');
        Storage::put('uploads/ignore.txt', 'dummy');

        // Expect Excel::import to be called for the .xls file
        Excel::shouldReceive('import')
            ->once()
            ->with(Mockery::type(InverterImport::class), 'uploads/valid.xls', null, ReaderType::XLS)
            ->andReturnTrue();

        // Act & Assert
        $this->artisan((new InverterCommand())->getName())
            ->expectsOutputToContain('Finding inverter data.')
            ->expectsOutputToContain('File not processed as it is not an excel .xls file:')
            ->expectsOutputToContain('uploads/ignore.txt')
            ->expectsOutputToContain('File processed and moved to:')
            ->expectsOutputToContain('uploads/processed/valid.xls')
            ->expectsOutputToContain('Successfully imported inverter data!')
            ->assertSuccessful();

        // Assert file was moved
        Storage::assertMissing('uploads/valid.xls');
        Storage::assertExists('uploads/processed/valid.xls');
    }
}
