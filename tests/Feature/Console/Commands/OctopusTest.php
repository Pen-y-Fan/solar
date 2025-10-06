<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\Octopus as OctopusCommand;
use App\Domain\Energy\Actions\AgileExport;
use App\Domain\Energy\Actions\AgileImport;
use App\Domain\Energy\Actions\OctopusExport;
use App\Domain\Energy\Actions\OctopusImport;
use App\Support\Actions\ActionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OctopusTest extends TestCase
{
    use RefreshDatabase;

    public function testOctopusCommandRunsAllActionsAndOutputsMessages(): void
    {
        // Bind fakes that return success without doing anything
        $this->app->instance(OctopusImport::class, new class extends OctopusImport
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });
        $this->app->instance(OctopusExport::class, new class extends OctopusExport
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });
        $this->app->instance(AgileImport::class, new class extends AgileImport
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });
        $this->app->instance(AgileExport::class, new class extends AgileExport
        {
            public function execute(): ActionResult
            {
                return ActionResult::success();
            }
        });

        $this->artisan((new OctopusCommand())->getName())
            ->expectsOutputToContain('Running Octopus action!')
            ->expectsOutputToContain('Octopus import has been fetched!')
            ->expectsOutputToContain('Octopus export has been fetched!')
            ->expectsOutputToContain('Octopus Agile import has been fetched!')
            ->expectsOutputToContain('Octopus Agile export has been fetched!')
            ->assertSuccessful();
    }
}
