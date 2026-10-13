<?php

namespace Lathe\Filters;

use DateTimeImmutable;
use Lathe\Http\Requests\LatheRequest;

/**
 * A date range: filter[key][from] and filter[key][to] as ISO dates, either side may be empty.
 */
abstract class DateFilter extends Filter
{
    public function component(): string
    {
        return 'date-filter';
    }

    /**
     * @return array{from: string|null, to: string|null}
     */
    public function normalize(LatheRequest $request, mixed $value, bool $remote): array
    {
        if (! is_array($value) || array_diff_key($value, ['from' => 0, 'to' => 0]) !== []) {
            $this->invalid();
        }

        return ['from' => $this->date($value['from'] ?? ''), 'to' => $this->date($value['to'] ?? '')];
    }

    protected function date(mixed $side): ?string
    {
        if ($side === '') {
            return null;
        }

        // The round trip refuses dates PHP would roll over, such as 2026-02-30.
        $date = is_string($side) ? DateTimeImmutable::createFromFormat('!Y-m-d', $side) : false;

        if ($date === false || $date->format('Y-m-d') !== $side) {
            $this->invalid();
        }

        return $side;
    }
}
