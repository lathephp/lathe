<?php

namespace Lathe\Actions\Import;

use Illuminate\Support\Str;

/**
 * Guesses which import column each file header belongs to.
 */
class ColumnMapper
{
    protected const int MAX_FUZZY_HEADER_LENGTH = 20;

    protected const int MAX_DISTANCE = 2;

    /**
     * @param  list<string>  $headers
     * @param  list<ImportColumn>  $columns
     * @return array<int, string|null> header index => column name, null to skip
     */
    public static function autoMap(array $headers, array $columns): array
    {
        $candidates = array_values(array_filter($columns, fn (ImportColumn $c) => ! $c->ignoresAutoMapping()));
        $normalized = array_map(self::normalize(...), $headers);
        $map = array_fill_keys(array_keys($normalized), null);
        $used = [];

        foreach (['getName', 'getLabel'] as $reader) {
            foreach ($normalized as $index => $header) {
                if ($map[$index] !== null) {
                    continue;
                }

                foreach ($candidates as $column) {
                    if (! isset($used[$column->getName()]) && self::normalize($column->$reader()) === $header) {
                        $map[$index] = $column->getName();
                        $used[$column->getName()] = true;

                        break;
                    }
                }
            }
        }

        foreach ($normalized as $index => $header) {
            if ($map[$index] !== null || strlen($header) > self::MAX_FUZZY_HEADER_LENGTH) {
                continue;
            }

            $best = null;
            $bestDistance = self::MAX_DISTANCE + 1;

            foreach ($candidates as $column) {
                if (isset($used[$column->getName()])) {
                    continue;
                }

                $distance = min(
                    levenshtein($header, self::normalize($column->getName())),
                    levenshtein($header, self::normalize($column->getLabel())),
                );

                if ($distance >= 1 && $distance < $bestDistance) {
                    $best = $column->getName();
                    $bestDistance = $distance;
                }
            }

            if ($best !== null) {
                $map[$index] = $best;
                $used[$best] = true;
            }
        }

        return $map;
    }

    /**
     * An all-caps header such as SKU is lowered first, or snake case would split it into s_k_u.
     */
    protected static function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === Str::upper($value)) {
            $value = Str::lower($value);
        }

        return Str::lower(Str::snake($value));
    }
}
