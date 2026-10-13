<?php

namespace Lathe\Field\Concerns;

use Closure;
use Lathe\Field\BadgeField;
use Lathe\Field\Field;
use Lathe\Field\IconField;
use Lathe\Field\LineField;
use Lathe\Field\SparklineField;
use Lathe\Field\StackField;
use Lathe\Field\StatusField;

/**
 * The factories of the badge, status, icon, sparkline, stack and line fields.
 */
trait DisplayFactories
{
    public static function badge(string $name, string|Closure|null $attribute = null): BadgeField
    {
        return new BadgeField($name, $attribute);
    }

    public static function status(string $name, string|Closure|null $attribute = null): StatusField
    {
        return new StatusField($name, $attribute);
    }

    public static function icon(string $name, string|Closure|null $attribute = null): IconField
    {
        return new IconField($name, $attribute);
    }

    public static function sparkline(string $name, ?string $attribute = null): SparklineField
    {
        return new SparklineField($name, $attribute);
    }

    /**
     * @param  list<Field|Closure>  $lines
     */
    public static function stack(string $name, ?string $attribute = null, array $lines = []): StackField
    {
        return (new StackField($name, $attribute))->lines($lines);
    }

    public static function line(string $name, string|Closure|null $attribute = null): LineField
    {
        return new LineField($name, $attribute);
    }
}
