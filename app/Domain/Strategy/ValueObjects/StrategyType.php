<?php

declare(strict_types=1);

namespace App\Domain\Strategy\ValueObjects;

/**
 * Value object representing strategy types for energy management
 */
class StrategyType
{
    /**
     * Strategy type constants
     */
    public const NONE = 0;
    public const CHARGE = 1;
    public const DISCHARGE = 2;
    public const HOLD = 3;

    /**
     * @param int|null $strategy1 Primary strategy type
     * @param int|null $strategy2 Secondary strategy type
     * @param int|null $manualStrategy Manually set strategy (if applicable)
     */
    public function __construct(
        public readonly ?int $strategy1 = null,
        public readonly ?int $strategy2 = null,
        public readonly ?int $manualStrategy = null
    ) {
        $this->validateStrategy($strategy1);
        $this->validateStrategy($strategy2);
        $this->validateStrategy($manualStrategy);
    }

    /**
     * Validate that a strategy value is valid
     */
    private function validateStrategy(?int $strategy): void
    {
        if ($strategy !== null && !in_array($strategy, [self::NONE, self::CHARGE, self::DISCHARGE, self::HOLD], true)) {
            throw new \InvalidArgumentException('Invalid strategy type');
        }
    }

    /**
     * Create from an array with 'strategy1', 'strategy2', and 'strategy_manual' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            strategy1: isset($data['strategy1']) ? (int) $data['strategy1'] : null,
            strategy2: isset($data['strategy2']) ? (int) $data['strategy2'] : null,
            manualStrategy: isset($data['strategy_manual']) ? (int) $data['strategy_manual'] : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'strategy1' => $this->strategy1,
            'strategy2' => $this->strategy2,
            'strategy_manual' => $this->manualStrategy,
        ];
    }

    /**
     * Check if manual strategy is set
     */
    public function hasManualStrategy(): bool
    {
        return $this->manualStrategy !== null;
    }

    /**
     * Get the effective strategy (manual if set, otherwise strategy1)
     */
    public function getEffectiveStrategy(): ?int
    {
        return $this->manualStrategy ?? $this->strategy1;
    }

    /**
     * Check if the effective strategy is to charge
     */
    public function isChargeStrategy(): bool
    {
        return $this->getEffectiveStrategy() === self::CHARGE;
    }

    /**
     * Check if the effective strategy is to discharge
     */
    public function isDischargeStrategy(): bool
    {
        return $this->getEffectiveStrategy() === self::DISCHARGE;
    }

    /**
     * Check if the effective strategy is to hold
     */
    public function isHoldStrategy(): bool
    {
        return $this->getEffectiveStrategy() === self::HOLD;
    }

    /**
     * Get the strategy name for a given strategy value
     */
    public static function getStrategyName(?int $strategy): string
    {
        return match ($strategy) {
            self::NONE => 'None',
            self::CHARGE => 'Charge',
            self::DISCHARGE => 'Discharge',
            self::HOLD => 'Hold',
            default => 'Unknown',
        };
    }

    /**
     * Get the name of the effective strategy
     */
    public function getEffectiveStrategyName(): string
    {
        return self::getStrategyName($this->getEffectiveStrategy());
    }
}
