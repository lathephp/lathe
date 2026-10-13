<?php

namespace Lathe\Field;

use BackedEnum;
use Collator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lathe\Lathe;
use Locale;
use LogicException;
use ResourceBundle;
use UnitEnum;

/**
 * Turns what a developer passes to options() into the list the client draws, and holds the
 * country and timezone lists. Both lists live in the request attributes, never in a static.
 */
class OptionList
{
    /** The most options a field sends; a longer list belongs in a relation field. */
    public const int LIMIT = 1000;

    /** ICU region codes that are not in ISO 3166-1: groupings, reserved codes and Sark. */
    private const array NOT_ISO = ['AC', 'CP', 'CQ', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'XK', 'ZZ'];

    /**
     * @return list<array{value: string, label: string, group?: string, color?: string, icon?: string}>
     */
    public static function normalize(mixed $source, string $field): array
    {
        $options = self::flatten($source);

        if (count($options) > self::LIMIT) {
            throw new LogicException(Lathe::__('The :field field has more than 1,000 options. Use a relation field instead.', ['field' => $field]));
        }

        return $options;
    }

    /**
     * ISO 3166-1 alpha-2 code to English name, sorted by name.
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        return self::remembered('lathe.countries', function (): array {
            $regions = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');
            $names = [];

            foreach ($regions instanceof ResourceBundle ? $regions : [] as $code => $unused) {
                if (is_string($code) && preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, self::NOT_ISO, true)) {
                    $names[$code] = (string) Locale::getDisplayRegion("-{$code}", 'en');
                }
            }

            (new Collator('en'))->asort($names);

            return $names;
        });
    }

    /**
     * @return list<string>
     */
    public static function timezones(): array
    {
        return self::remembered('lathe.timezones', fn (): array => timezone_identifiers_list());
    }

    /**
     * @template T of array
     *
     * @param  \Closure(): T  $build
     * @return T
     */
    private static function remembered(string $key, \Closure $build): array
    {
        $attributes = Evaluator::currentRequest()?->attributes;
        $cached = $attributes?->get($key);

        if (is_array($cached)) {
            /** @var T $cached */
            return $cached;
        }

        $built = $build();
        $attributes?->set($key, $built);

        return $built;
    }

    /**
     * @return list<array{value: string, label: string, group?: string, color?: string, icon?: string}>
     */
    private static function flatten(mixed $source, ?string $group = null): array
    {
        if (is_string($source) && enum_exists($source)) {
            return self::fromEnum($source, $group);
        }

        if (! is_array($source)) {
            throw new InvalidArgumentException('Options must be an array or an enum class.');
        }

        $options = [];
        $list = array_is_list($source);

        foreach ($source as $key => $item) {
            if ($item instanceof OptionGroup) {
                array_push($options, ...self::flatten($item->options, $item->label));

                continue;
            }

            $label = is_scalar($item) ? (string) $item : '';
            $options[] = self::option($list ? $label : (string) $key, $label, $group);
        }

        return $options;
    }

    /**
     * @param  class-string<UnitEnum>  $enum
     * @return list<array{value: string, label: string, group?: string, color?: string, icon?: string}>
     */
    private static function fromEnum(string $enum, ?string $group): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $label = method_exists($case, 'label') ? $case->label() : Str::headline($case->name);
            $extra = [];

            foreach (['color', 'icon'] as $method) {
                $given = method_exists($case, $method) ? DefaultLifecycle::wire($case->{$method}()) : null;

                if (is_scalar($given)) {
                    $extra[$method] = (string) $given;
                }
            }

            $options[] = self::option((string) ($case instanceof BackedEnum ? $case->value : $case->name), is_scalar($label) ? (string) $label : '', $group, $extra);
        }

        return $options;
    }

    /**
     * @param  array{color?: string, icon?: string}  $extra
     * @return array{value: string, label: string, group?: string, color?: string, icon?: string}
     */
    private static function option(string $value, string $label, ?string $group, array $extra = []): array
    {
        return ['value' => $value, 'label' => $label, ...($group === null ? [] : ['group' => $group]), ...$extra];
    }
}
