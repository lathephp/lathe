<?php

namespace Lathe\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Lathe\LatheManager;

/**
 * Converts UTC moments for display in the user's timezone. Storage and JSON stay UTC.
 */
class Timezone
{
    public function __construct(protected LatheManager $lathe)
    {
        //
    }

    /**
     * The user's timezone, else the app's.
     */
    public function forRequest(Request $request): string
    {
        $timezone = $this->lathe->resolveUserTimezone($request) ?? config('app.timezone');

        return is_string($timezone) ? $timezone : 'UTC';
    }

    /**
     * The calendar day, Y-m-d, a moment falls on in the zone.
     */
    public function localDay(CarbonInterface $moment, string $timezone): string
    {
        return CarbonImmutable::instance($moment)->setTimezone($timezone)->format('Y-m-d');
    }

    /**
     * The UTC start (inclusive) and end (exclusive) of a local calendar day, which is
     * shorter or longer than 24 hours on a daylight saving change.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayBounds(string $day, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $day, $timezone);

        if (! $start instanceof CarbonImmutable) {
            throw new \InvalidArgumentException("{$day} is not a Y-m-d date.");
        }

        return [$start->utc(), $start->addDay()->utc()];
    }
}
