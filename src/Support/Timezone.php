<?php

namespace Lathe\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\Request;
use Lathe\LatheManager;

/**
 * The two zones a moment meets. The database holds a datetime as wall time in the app's zone,
 * as forms and Laravel's own timestamps write it; the user reads it, and cuts days, in theirs.
 * JSON carries moments as UTC.
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
     * The zone a datetime column's text is wall time in: the app's.
     */
    public function storage(): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) ? $timezone : 'UTC';
    }

    /**
     * A moment as the text a datetime column holds, to compare the column with.
     */
    public function stored(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->storage())->format('Y-m-d H:i:s');
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
