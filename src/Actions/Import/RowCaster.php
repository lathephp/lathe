<?php

namespace Lathe\Actions\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lathe\Field\DateField;
use Lathe\Support\Timezone;
use Throwable;

/**
 * Cleans and casts the cells of one import row. Relationship lookups are not here: the job
 * resolves them with one query per chunk. A date cell is read in the user's zone and lands in
 * the app's, as a form saves it; on a column the model casts to a day it stays that calendar day.
 */
class RowCaster
{
    public function __construct(protected string $timezone, protected ?Model $model = null)
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
            'date' => $this->toDate($value, $column->getDateFormat(), $this->model !== null && DateField::castsToDay($this->model, $column->getName())),
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

    protected function toDate(mixed $value, ?string $format, bool $day = false): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->stored(Carbon::instance($value), $day);
        }

        $text = is_scalar($value) ? (string) $value : '';

        // PHP rolls a day the calendar lacks, 2026-02-30, over into the next month; refuse it.
        if (($format === null ? date_parse($text) : date_parse_from_format($format, $text))['warning_count'] > 0) {
            throw new RowCastException('Invalid date');
        }

        try {
            $date = $format === null
                ? Carbon::parse($text, $this->timezone)
                : Carbon::createFromFormat($format, $text, $this->timezone);
        } catch (Throwable) {
            throw new RowCastException('Invalid date');
        }

        // createFromFormat returns false on a mismatch in older Carbon versions.
        if (! $date instanceof Carbon) {
            throw new RowCastException('Invalid date');
        }

        return $this->stored($date, $day);
    }

    /**
     * The moment in the app's zone, or its calendar day there at midnight.
     */
    protected function stored(Carbon $date, bool $day): Carbon
    {
        $storage = app(Timezone::class)->storage();

        if ($day) {
            return Carbon::createFromFormat('!Y-m-d', $date->format('Y-m-d'), $storage) ?: $date;
        }

        return $date->setTimezone($storage);
    }
}
