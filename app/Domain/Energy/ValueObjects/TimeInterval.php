<?php

declare(strict_types=1);

namespace App\Domain\Energy\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Value object representing a time interval with start and end times
 */
class TimeInterval
{
    /**
     * @param CarbonImmutable|null $from Start time of the interval
     * @param CarbonImmutable|null $to End time of the interval
     */
    public function __construct(
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null
    ) {
        if ($from !== null && $to !== null && $from->greaterThanOrEqualTo($to)) {
            throw new InvalidArgumentException('Start time must be before end time');
        }
    }

    /**
     * Create from an array with 'valid_from' and 'valid_to' keys
     */
    public static function fromArray(array $data): self
    {
        $from = isset($data['valid_from']) ?
            (is_string($data['valid_from']) ?
                CarbonImmutable::parse($data['valid_from']) :
                $data['valid_from']) :
            null;

        $to = isset($data['valid_to']) ?
            (is_string($data['valid_to']) ?
                CarbonImmutable::parse($data['valid_to']) :
                $data['valid_to']) :
            null;

        return new self(
            from: $from,
            to: $to
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'valid_from' => $this->from,
            'valid_to' => $this->to,
        ];
    }

    /**
     * Get the duration of the interval in minutes
     */
    public function getDurationInMinutes(): ?int
    {
        if ($this->from === null || $this->to === null) {
            return null;
        }

        return (int) $this->from->diffInMinutes($this->to);
    }

    /**
     * Get the duration of the interval in hours
     */
    public function getDurationInHours(): ?float
    {
        if ($this->from === null || $this->to === null) {
            return null;
        }

        return $this->from->diffInMinutes($this->to) / 60.0;
    }

    /**
     * Check if the interval contains the given time
     */
    public function contains(CarbonImmutable $time): bool
    {
        if ($this->from === null || $this->to === null) {
            return false;
        }

        return $time->greaterThanOrEqualTo($this->from) && $time->lessThan($this->to);
    }

    /**
     * Check if the interval overlaps with another interval
     */
    public function overlaps(self $other): bool
    {
        if ($this->from === null || $this->to === null || $other->from === null || $other->to === null) {
            return false;
        }

        return $this->from->lessThan($other->to) && $this->to->greaterThan($other->from);
    }
}
