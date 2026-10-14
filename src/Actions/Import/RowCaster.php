<?php

namespace Lathe\Actions\Import;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cleans and casts the cells of one import row. Relationship lookups are not here: the job
 * resolves them with one query per chunk.
 */
class RowCaster
{
    public function __construct(protected string $timezone)
    {
        //
    }

    /**
     * Cast the cells present in the row; a column the row lacks stays absent.
     *
     * @param  array<string, ImportColumn>  $columns
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function castRow(array $columns, array $row): array
    {
        foreach ($columns as $name => $column) {
            if (array_key_exists($name, $row)) {
                $row[$name] = $this->cast($column, $row[$name]);
            }
        }

        return $row;
    }

    public function cast(ImportColumn $column, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = $this->clean($value);

            if ($value === '' && $column->isNullable()) {
                $value = null;
            }
        }

        if ($value === null || $value === '') {
            return $value;
        }

        $value = match ($column->getCast()) {
            'boolean' => $this->toBoolean($value),
            'numeric' => $this->toNumber($value, $column->getDecimalPlaces()),
            'date' => $this->toDate($value, $column->getDateFormat()),
            default => $value,
        };

        $callback = $column->getCastUsing();

        return $callback === null ? $value : $callback($value);
    }

    protected function clean(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(is_scalar($value) ? (string) $value : ''), ['1', 'true', 'yes', 'on', 'y'], true);
    }

    protected function toNumber(mixed $value, ?int $decimalPlaces): int|float|null
    {
        if (! is_int($value) && ! is_float($value)) {
            $text = preg_replace('/[^0-9.\-]/', '', is_scalar($value) ? (string) $value : '') ?? '';

            if (! preg_match('/\d/', $text)) {
                return null;
            }

            $value = (float) $text;
        }

        if ($decimalPlaces !== null) {
            $value = round($value, $decimalPlaces);
        }

        return is_float($value) && floor($value) === $value ? (int) $value : $value;
    }

    protected function toDate(mixed $value, ?string $format): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        try {
            $date = $format === null
                ? Carbon::parse(is_scalar($value) ? (string) $value : '', $this->timezone)
                : Carbon::createFromFormat($format, is_scalar($value) ? (string) $value : '', $this->timezone);
        } catch (Throwable) {
            throw new RowCastException('Invalid date');
        }

        // createFromFormat returns false on a mismatch in older Carbon versions.
        if (! $date instanceof Carbon) {
            throw new RowCastException('Invalid date');
        }

        return $date->utc();
    }
}
