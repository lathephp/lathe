<?php

namespace Lathe\Menu;

use Closure;
use JsonSerializable;
use Stringable;

/**
 * A small label beside a menu entry, such as a count.
 *
 * @phpstan-consistent-constructor
 */
class Badge implements JsonSerializable
{
    public const string INFO = 'info';

    public const string SUCCESS = 'success';

    public const string WARNING = 'warning';

    public const string DESTRUCTIVE = 'destructive';

    protected ?string $tooltip = null;

    public function __construct(protected Closure|Stringable|string $value, protected string $type = self::INFO)
    {
        //
    }

    public static function info(Closure|Stringable|string $value): static
    {
        return new static($value, self::INFO);
    }

    public static function success(Closure|Stringable|string $value): static
    {
        return new static($value, self::SUCCESS);
    }

    public static function warning(Closure|Stringable|string $value): static
    {
        return new static($value, self::WARNING);
    }

    public static function destructive(Closure|Stringable|string $value): static
    {
        return new static($value, self::DESTRUCTIVE);
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function tooltip(string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    /**
     * The badge text. A closure runs here, when the menu JSON is built.
     */
    public function resolveValue(): string
    {
        $value = $this->value instanceof Closure ? ($this->value)() : $this->value;

        return is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
    }

    /**
     * @return array{value: string, type: string, tooltip?: string}
     */
    public function jsonSerialize(): array
    {
        $json = ['value' => $this->resolveValue(), 'type' => $this->type];

        if ($this->tooltip !== null) {
            $json['tooltip'] = $this->tooltip;
        }

        return $json;
    }
}
