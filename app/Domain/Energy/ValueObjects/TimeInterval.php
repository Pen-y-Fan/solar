<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Value object representing a time interval with start and end times
 */
class TimeInterval
{
    /**
     * @param CarbonImmutable $start Start time of the interval
     * @param CarbonImmutable $end End time of the interval
     */
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end
    ) {
    }

    /**
     * Create from an array with 'start' and 'end' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            start: CarbonImmutable::parse($data['start']),
            end: CarbonImmutable::parse($data['end'])
        );
    }

    /**
     * Create from start and end Carbon dates
     */
    public static function fromCarbon(CarbonInterface $start, CarbonInterface $end): self
    {
        return new self(
            start: $start instanceof CarbonImmutable ? $start : $start->toImmutable(),
            end: $end instanceof CarbonImmutable ? $end : $end->toImmutable()
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
        ];
    }

    /**
     * Get the duration of the interval in seconds
     */
    public function getDuration(): float
    {
        return $this->end->diffInSeconds($this->start);
    }

    /**
     * Check if a given time is within this interval
     */
    public function contains(CarbonInterface $time): bool
    {
        return $time->between($this->start, $this->end);
    }
}
