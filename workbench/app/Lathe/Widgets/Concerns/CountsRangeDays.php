<?php

namespace Workbench\App\Lathe\Widgets\Concerns;

use Carbon\CarbonImmutable;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\Timezone;

/**
 * The first moment of the dashboard's range, as the value and trend cards count it: today and
 * the days before it in the user's zone, so every Sales card counts the same orders.
 */
trait CountsRangeDays
{
    protected function rangeStart(LatheRequest $request): CarbonImmutable
    {
        $days = max(1, (int) $request->query('range', '30'));

        return CarbonImmutable::now(app(Timezone::class)->forRequest($request))->startOfDay()->subDays($days - 1)->setTimezone(app(Timezone::class)->storage());
    }
}
