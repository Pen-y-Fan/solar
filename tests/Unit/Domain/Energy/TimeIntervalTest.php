<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Energy;

use App\Domain\Energy\ValueObjects\TimeInterval;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeIntervalTest extends TestCase
{
    public function testFromArrayAndToArrayRoundTripWithStrings(): void
    {
        $from = '2024-01-01T10:00:00+00:00';
        $to   = '2024-01-01T10:30:00+00:00';

        $vo = TimeInterval::fromArray([
            'valid_from' => $from,
            'valid_to' => $to,
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $vo->from);
        $this->assertInstanceOf(CarbonImmutable::class, $vo->to);
        $this->assertSame(30, $vo->getDurationInMinutes());
        $this->assertEqualsWithDelta(0.5, $vo->getDurationInHours(), 1e-9);

        $arr = $vo->toArray();
        $this->assertInstanceOf(CarbonImmutable::class, $arr['valid_from']);
        $this->assertInstanceOf(CarbonImmutable::class, $arr['valid_to']);
    }

    public function testConstructorThrowsWhenStartNotBeforeEnd(): void
    {
        $from = new CarbonImmutable('2024-01-01 10:00:00', 'UTC');
        $toEqual = new CarbonImmutable('2024-01-01 10:00:00', 'UTC');
        $toBefore = new CarbonImmutable('2024-01-01 09:59:59', 'UTC');

        $this->expectException(InvalidArgumentException::class);
        new TimeInterval($from, $toEqual);
    }

    public function testContainsIsInclusiveOfStartAndExclusiveOfEnd(): void
    {
        $from = new CarbonImmutable('2024-01-01 10:00:00', 'UTC');
        $to   = new CarbonImmutable('2024-01-01 10:30:00', 'UTC');
        $vo   = new TimeInterval($from, $to);

        $this->assertTrue($vo->contains($from));
        $this->assertTrue($vo->contains($from->addMinute()));
        $this->assertFalse($vo->contains($to));
        $this->assertFalse($vo->contains($to->addSecond()));
    }

    public function testOverlapsTrueWhenIntervalsOverlapAndFalseWhenTouchingOnly(): void
    {
        $a = new TimeInterval(
            new CarbonImmutable('2024-01-01 10:00:00', 'UTC'),
            new CarbonImmutable('2024-01-01 10:30:00', 'UTC')
        );
        $b = new TimeInterval(
            new CarbonImmutable('2024-01-01 10:15:00', 'UTC'),
            new CarbonImmutable('2024-01-01 10:45:00', 'UTC')
        );
        $this->assertTrue($a->overlaps($b));
        $this->assertTrue($b->overlaps($a));

        // Touching at boundary should be false since [from, to)
        $c = new TimeInterval(
            new CarbonImmutable('2024-01-01 10:30:00', 'UTC'),
            new CarbonImmutable('2024-01-01 11:00:00', 'UTC')
        );
        $this->assertFalse($a->overlaps($c));
        $this->assertFalse($c->overlaps($a));
    }

    public function testNullEndpointsDisableContainsAndOverlaps(): void
    {
        $from = new CarbonImmutable('2024-01-01 10:00:00', 'UTC');
        $open = new TimeInterval($from, null);
        $this->assertNull($open->getDurationInMinutes());
        $this->assertNull($open->getDurationInHours());
        $this->assertFalse($open->contains($from));

        $closed = new TimeInterval($from, $from->addMinutes(30));
        $this->assertFalse($open->overlaps($closed));
    }
}
