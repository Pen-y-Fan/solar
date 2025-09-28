<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use Illuminate\Support\Collection;

/**
 * Simple fake for StrategyManualSeriesQuery that records invocation.
 */
final class FakeStrategyManualSeriesQuery
{
    public bool $called = false;

    /** @param Collection<int, mixed> $ret */
    public function __construct(private Collection $ret, private Collection $expected)
    {
    }

    /** @return Collection<int, array<string, mixed>> */
    public function run($arg): Collection
    {
        $this->called = true;
        if (!($arg instanceof Collection) || $arg->count() !== $this->expected->count()) {
            throw new \RuntimeException('Unexpected collection passed to query');
        }
        return $this->ret;
    }
}
