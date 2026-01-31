<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Solis\Actions;

use App\Domain\Solis\Actions\InverterListAction;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InverterListActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('solis.key_id', '');
        Config::set('solis.key_secret', '');
        Config::set('solis.api_url', '');
    }

    public function testMissingConfigReturnsFailedResult(): void
    {
        $action = new InverterListAction();

        $result = $action->execute();

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Missing Solis configuration', $result->getMessage());
    }
}
