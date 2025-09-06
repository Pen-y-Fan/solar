<?php

declare(strict_types=1);

namespace App\Application\Queries\Strategy;

use App\Domain\Strategy\Models\Strategy;
use Illuminate\Support\Collection;

final class LatestStrategiesQuery
{
    /**
     * Return the latest N Strategy rows for dashboard listing.
     *
     * @return Collection<array{id:int, period:string|null, created_at:string}>
     */
    public function run(int $limit = 5): Collection
    {
        $rows = Strategy::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'period', 'created_at']);

        /** @var Collection<int, array{id:int, period:string|null, created_at:string}> $mapped */
        $mapped = $rows->map(static function (Strategy $s): array {
            return [
                'id' => (int) $s->id,
                'period' => $s->period !== null ? (string) $s->period : null,
                'created_at' => $s->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        return $mapped;
    }
}
