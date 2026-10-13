<?php

namespace Lathe\Groups;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * One way to group the index rows: by a column of the model or of a to-one relation.
 */
class Group
{
    /** The key of the rows whose group value is null, on the server and in the client. */
    public const string NULL_KEY = '__lathe_null__';

    protected ?string $label = null;

    protected bool $date = false;

    protected bool $collapsible = false;

    /** @var 'asc'|'desc' */
    protected string $direction = 'asc';

    protected ?Closure $title = null;

    protected ?Closure $description = null;

    protected bool $prefixed = false;

    protected string $timezone = 'UTC';

    final public function __construct(protected string $column, protected ?string $id = null)
    {
        //
    }

    public static function make(string $column, ?string $id = null): static
    {
        return new static($column, $id);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Bucket the rows by calendar day; a datetime column by the user's local day.
     */
    public function date(bool $date = true): static
    {
        $this->date = $date;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * The direction when ?group= names none.
     *
     * @param  'asc'|'desc'  $direction
     */
    public function orderDirection(string $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    /**
     * @param  Closure(Model): mixed  $title
     */
    public function titleUsing(Closure $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @param  Closure(Model): mixed  $description
     */
    public function description(Closure $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Show "Status: paid" in place of "paid".
     */
    public function titlePrefixedWithLabel(bool $prefixed = true): static
    {
        $this->prefixed = $prefixed;

        return $this;
    }

    /**
     * The zone whose calendar days a datetime column buckets by.
     *
     * @internal
     */
    public function inTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function id(): string
    {
        return $this->id ?? $this->column;
    }

    public function column(): string
    {
        return $this->column;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline(str_replace('.', '_', $this->id()));
    }

    public function isDate(): bool
    {
        return $this->date;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    /**
     * @return 'asc'|'desc'
     */
    public function getOrderDirection(): string
    {
        return $this->direction;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * Whether a date() group reads a datetime column, which buckets by the user's local day.
     */
    public function bucketsLocalDay(Model $model): bool
    {
        return $this->date && ! $model->hasCast($this->attribute(), ['date', 'immutable_date']);
    }

    /**
     * The group key of a row.
     */
    public function keyFor(Model $model): string
    {
        $value = data_get($model, $this->column);

        // A datetime moment lands on the calendar day of the user's zone.
        if ($this->date && $value instanceof DateTimeInterface && $this->bucketsLocalDay($this->owner($model))) {
            $value = CarbonImmutable::instance($value)->setTimezone($this->timezone);
        }

        return $this->normalizeKey($value);
    }

    /**
     * One stable string for a group value, whether it came from a model or a GROUP BY row.
     */
    public function normalizeKey(mixed $value): string
    {
        return match (true) {
            $value === null => self::NULL_KEY,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format($this->date ? 'Y-m-d' : 'Y-m-d H:i:s'),
            // A day bucket comes back as `2026-03-08 00:00:00` on PostgreSQL.
            $this->date && is_string($value) => substr($value, 0, 10),
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    /**
     * The header title of a row's group.
     */
    public function titleFor(Model $model): string
    {
        $key = $this->keyFor($model);
        $title = $this->title !== null ? ($this->title)($model) : ($key === self::NULL_KEY ? '—' : $key);
        $title = is_scalar($title) ? (string) $title : '';

        return $this->prefixed ? "{$this->getLabel()}: {$title}" : $title;
    }

    public function descriptionFor(Model $model): ?string
    {
        $description = $this->description !== null ? ($this->description)($model) : null;

        return is_scalar($description) ? (string) $description : null;
    }

    /**
     * The relation the column is read through, `customer` for `customer.country`.
     */
    public function relation(): ?string
    {
        return str_contains($this->column, '.') ? Str::before($this->column, '.') : null;
    }

    /**
     * The column on the model that holds the value, `country` for `customer.country`.
     */
    public function attribute(): string
    {
        return Str::afterLast($this->column, '.');
    }

    /**
     * @return array{id: string, label: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id(), 'label' => $this->getLabel()];
    }

    protected function owner(Model $model): Model
    {
        $relation = $this->relation();
        $related = $relation === null ? $model : $model->getRelationValue($relation);

        return $related instanceof Model ? $related : $model;
    }
}
