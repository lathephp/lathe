<?php

namespace Lathe\Widgets;

/**
 * The rows or series a chart or table card shows, as RFC 4180 CSV with plain numbers.
 *
 * @internal
 */
class WidgetCsv
{
    /**
     * Whether the card can be downloaded as CSV.
     */
    public static function supports(Widget $widget): bool
    {
        return $widget instanceof TrendWidget || $widget instanceof BarWidget || $widget instanceof PartitionWidget
            || $widget instanceof PieWidget || $widget instanceof RadarWidget || $widget instanceof TableWidget;
    }

    /**
     * The CSV of the value the card sent, null when the value has no rows to write.
     *
     * @param  mixed  $value  the value of the card's response
     * @param  string  $column  the number column's name
     * @param  string  $group  the group column's name
     */
    public static function from(Widget $widget, mixed $value, string $column, string $group): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $rows = match (true) {
            $widget instanceof TrendWidget => self::pairs($value['trend'] ?? null, 'date', $column),
            $widget instanceof BarWidget => self::groups($value['bars'] ?? null, $group, $column),
            $widget instanceof PartitionWidget, $widget instanceof PieWidget => self::groups($value['value'] ?? null, $group, $column),
            $widget instanceof RadarWidget => self::radar($value),
            $widget instanceof TableWidget => self::table($value),
            default => null,
        };

        return $rows === null ? null : implode('', array_map(self::line(...), $rows));
    }

    /**
     * @return list<list<mixed>>|null
     */
    private static function pairs(mixed $points, string $key, string $column): ?array
    {
        if (! is_array($points)) {
            return null;
        }

        $rows = [[$key, $column]];

        foreach ($points as $label => $number) {
            $rows[] = [(string) $label, $number];
        }

        return $rows;
    }

    /**
     * @return list<list<mixed>>|null
     */
    private static function groups(mixed $items, string $group, string $column): ?array
    {
        if (! is_array($items)) {
            return null;
        }

        $rows = [[$group, $column]];

        foreach ($items as $item) {
            $rows[] = is_array($item) ? [$item['label'] ?? '', $item['value'] ?? ''] : ['', ''];
        }

        return $rows;
    }

    /**
     * One row per axis and one column per dataset.
     *
     * @param  array<mixed>  $value
     * @return list<list<mixed>>|null
     */
    private static function radar(array $value): ?array
    {
        $axes = $value['axes'] ?? null;
        $datasets = is_array($value['datasets'] ?? null) ? array_values($value['datasets']) : null;

        if (! is_array($axes) || $datasets === null) {
            return null;
        }

        $rows = [['axis', ...array_map(fn (mixed $set) => is_array($set) ? ($set['name'] ?? '') : '', $datasets)]];

        foreach (array_values($axes) as $index => $axis) {
            $rows[] = [
                is_array($axis) ? ($axis['label'] ?? '') : '',
                ...array_map(fn (mixed $set) => is_array($set) && is_array($set['data'] ?? null) ? ($set['data'][$index] ?? '') : '', $datasets),
            ];
        }

        return $rows;
    }

    /**
     * The declared column keys as the header, else the title and subtitle of metric rows.
     *
     * @param  array<mixed>  $value
     * @return list<list<mixed>>
     */
    private static function table(array $value): array
    {
        $columns = is_array($value['columns'] ?? null) ? $value['columns'] : [];
        $keys = array_values(array_map(fn (mixed $column) => is_array($column) && is_string($column['key'] ?? null) ? $column['key'] : '', $columns));
        $keys = $keys === [] ? ['title', 'subtitle'] : $keys;
        $rows = [$keys];

        foreach (is_array($value['rows'] ?? null) ? $value['rows'] : [] as $row) {
            $rows[] = array_map(fn (string $key) => is_array($row) ? ($row[$key] ?? '') : '', $keys);
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $fields
     */
    private static function line(array $fields): string
    {
        return implode(',', array_map(self::field(...), $fields))."\r\n";
    }

    private static function field(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $text = (string) $value;

            // A float past 15 digits casts to 1.0E+25, which is not a plain number.
            return str_contains($text, 'E') ? rtrim(rtrim(sprintf('%.10F', $value), '0'), '.') : $text;
        }

        $text = is_scalar($value) ? (string) $value : '';

        // A spreadsheet runs a cell that starts with one of these as a formula.
        if ($text !== '' && ! is_numeric($text) && in_array($text[0], ['=', '+', '-', '@'], true)) {
            $text = "'".$text;
        }

        return strpbrk($text, ",\"\r\n") === false ? $text : '"'.str_replace('"', '""', $text).'"';
    }
}
