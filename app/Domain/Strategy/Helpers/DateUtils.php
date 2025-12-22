<?php

namespace App\Domain\Strategy\Helpers;

use Illuminate\Support\Carbon;

class DateUtils
{
    public static function calculateDateRange(?string $date): array
    {
        $dateStr = $date ?: Carbon::now('Europe/London')->format('Y-m-d');
        $start = Carbon::parse($dateStr, 'Europe/London')
            ->subDay()->setTime(16, 0)->timezone('UTC');
        $end = $start->clone()->addDay();
        return [$start, $end];
    }
}
