<?php

namespace Tests\Unit\Domain\Energy\Models;

use App\Domain\Energy\Models\OutgoingOctopus;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class OutgoingOctopusTest extends TestCase
{
    public function testItReturns12POnOrAfter1StMarch2026(): void
    {
        $date = Carbon::createFromFormat('Y-m-d', '2026-03-01', 'UTC');
        $this->assertEquals(12.0, OutgoingOctopus::getRate($date));

        $date = Carbon::createFromFormat('Y-m-d', '2026-03-08', 'UTC');
        $this->assertEquals(12.0, OutgoingOctopus::getRate($date));
    }

    public function testItReturns15PBetween8ThJuly2025And1StMarch2026(): void
    {
        $date = Carbon::createFromFormat('Y-m-d', '2025-07-08', 'UTC');
        $this->assertEquals(15.0, OutgoingOctopus::getRate($date));

        $date = Carbon::createFromFormat('Y-m-d', '2026-02-28', 'UTC');
        $this->assertEquals(15.0, OutgoingOctopus::getRate($date));
    }

    public function testItReturnsModelValueBefore8ThJuly2025(): void
    {
        $date = Carbon::createFromFormat('Y-m-d', '2025-07-07', 'UTC');
        $this->assertEquals(5.5, OutgoingOctopus::getRate($date, 5.5));
    }

    public function testItReturns0IfNoModelValueBefore8ThJuly2025(): void
    {
        $date = Carbon::createFromFormat('Y-m-d', '2025-07-07', 'UTC');
        $this->assertEquals(0.0, OutgoingOctopus::getRate($date));
    }
}
