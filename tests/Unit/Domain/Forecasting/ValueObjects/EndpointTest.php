<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forecasting\ValueObjects;

use App\Domain\Forecasting\ValueObjects\Endpoint;
use PHPUnit\Framework\TestCase;

final class EndpointTest extends TestCase
{
    public function testEnumValuesAndHelpers(): void
    {
        $this->assertSame('forecast', Endpoint::FORECAST->value);
        $this->assertSame('actual', Endpoint::ACTUAL->value);

        $this->assertTrue(Endpoint::FORECAST->isForecast());
        $this->assertFalse(Endpoint::FORECAST->isActual());

        $this->assertTrue(Endpoint::ACTUAL->isActual());
        $this->assertFalse(Endpoint::ACTUAL->isForecast());
    }
}
