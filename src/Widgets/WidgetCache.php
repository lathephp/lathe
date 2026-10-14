<?php

namespace Lathe\Widgets;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The store behind widget values. Each entry carries the tag lathe:widgets plus the tables
 * it read, so a panel write to a table flushes the cards over it. A store without tags
 * keeps entries for their time to live only.
 */
class WidgetCache
{
    public const TAG = 'lathe:widgets';

    /**
     * The cached value at the key, or the computed one, stored under its tags.
     *
     * @param  Closure(): array{0: array<string, mixed>, 1: list<string>}  $compute  the value and the tags it read
     * @return array<string, mixed>
     */
    public function remember(string $key, DateTimeInterface|DateInterval|int $ttl, Closure $compute): array
    {
        if (! Cache::supportsTags()) {
            $value = Cache::get($key);

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                return $value;
            }

            [$value] = $compute();
            Cache::put($key, $value, $ttl);

            return $value;
        }

        // A tagged entry is found only under the tags it was stored with, which the value
        // learns as it runs, so a second entry under lathe:widgets alone holds them.
        $tags = Cache::tags([self::TAG])->get($key.':tags');
        $value = is_array($tags) ? Cache::tags(array_values(array_filter($tags, 'is_string')))->get($key) : null;

        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        [$value, $read] = $compute();
        $tags = array_values(array_unique([self::TAG, ...$read]));

        Cache::tags([self::TAG])->put($key.':tags', $tags, $ttl);
        Cache::tags($tags)->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Drop every cached value that read one of the tables.
     *
     * @param  list<string>  $tables
     */
    public function flushTables(array $tables): void
    {
        if ($tables !== [] && Cache::supportsTags()) {
            Cache::tags($tables)->flush();
        }
    }
}
