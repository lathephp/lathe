<?php

namespace Lathe\Support;

use Closure;
use Illuminate\Support\Arr;
use Lathe\Theme\ThemeStyleGenerator;

/**
 * Checks the lathe config: boot throws on the first error, lathe:doctor lists them all.
 */
class ConfigValidator
{
    /**
     * Read the defaults the package ships.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        /** @var array<string, mixed> */
        return require dirname(__DIR__, 2).'/config/lathe.php';
    }

    /**
     * Every problem in the config, each naming key, file and fix.
     *
     * @param  array<array-key, mixed>  $config
     * @return list<string>
     */
    public function errors(array $config): array
    {
        $max = Arr::get($config, 'limits.max_per_page');
        $max = is_int($max) && $max > 0 ? $max : 100;
        $errors = [];

        foreach ($this->rules($max) as $key => $rule) {
            if (! Arr::has($config, $key)) {
                continue;
            }

            $problem = $rule(Arr::get($config, $key));

            if ($problem !== null) {
                $errors[] = "config/lathe.php: {$key}: {$problem}";
            }
        }

        return $errors;
    }

    /**
     * Keys the package does not know, in dot notation, sorted.
     *
     * @param  array<array-key, mixed>  $config
     * @return list<string>
     */
    public function unknownKeys(array $config): array
    {
        $unknown = $this->unknownIn($config, static::defaults(), '');
        sort($unknown);

        return $unknown;
    }

    /**
     * @param  array<array-key, mixed>  $given
     * @param  array<array-key, mixed>  $known
     * @return list<string>
     */
    protected function unknownIn(array $given, array $known, string $prefix): array
    {
        $unknown = [];

        foreach ($given as $key => $value) {
            $path = $prefix.$key;

            if (! array_key_exists($key, $known)) {
                $unknown[] = $path;
            } elseif (is_array($value) && is_array($known[$key]) && ! array_is_list($known[$key])) {
                $unknown = [...$unknown, ...$this->unknownIn($value, $known[$key], $path.'.')];
            }
        }

        return $unknown;
    }

    /**
     * One check per key; each returns null when the value is fine.
     *
     * @return array<string, Closure(mixed): ?string>
     */
    protected function rules(int $max): array
    {
        $string = $this->string();
        $nullableString = $this->nullable($this->string());
        $bool = $this->boolean();
        $positive = $this->integer(1);

        return [
            'name' => $string,
            'version' => fn (mixed $v) => $v === null || $v === false || is_string($v)
                ? null : 'must be null, false or a string; got '.$this->show($v).'. Use null for the installed version or false to hide it.',
            'domain' => $nullableString,
            'path' => fn (mixed $v) => is_string($v) && str_starts_with($v, '/')
                ? null : 'must be a path that starts with /; got '.$this->show($v).". Set it to a path such as '/lathe'.",
            'guard' => $nullableString,
            'passwords' => $nullableString,
            'middleware' => $this->stringList(),
            'api_middleware' => $this->stringList(),
            'initial_path' => $nullableString,
            'currency' => fn (mixed $v) => is_string($v) && preg_match('/^[A-Z]{3}$/', $v) === 1
                ? null : 'must be a three-letter ISO 4217 code; got '.$this->show($v).". Set it to a code such as 'USD'.",
            'pagination.type' => $this->oneOf(['links', 'simple', 'load-more']),
            'pagination.per_page' => fn (mixed $v) => is_int($v) && $v >= 1 && $v <= $max
                ? null : "must be an integer from 1 to {$max}; got ".$this->show($v).'. Set it inside that range or raise limits.max_per_page.',
            'pagination.per_page_options' => function (mixed $v) use ($max): ?string {
                if (! is_array($v) || ! array_is_list($v) || $v === []) {
                    return 'must be a list of integers; got '.$this->show($v).'. Set it to a list such as [25, 50, 100].';
                }

                foreach ($v as $option) {
                    if (! is_int($option) || $option < 1 || $option > $max) {
                        return "every value must be an integer from 1 to {$max}; got ".$this->show($option).'. Set limits.max_per_page higher or remove '.$this->show($option).'.';
                    }
                }

                return null;
            },
            'limits.max_per_page' => $positive,
            'limits.count_cap' => $positive,
            'limits.picker_results' => $positive,
            'limits.search_term_chars' => $positive,
            'limits.search_term_words' => $positive,
            'limits.filter_remote_threshold' => $positive,
            'limits.bulk_inline_max' => $positive,
            'limits.id_list_max' => $positive,
            'limits.metrics_ttl' => $positive,
            'limits.repeater_rows' => $positive,
            'limits.key_value_rows' => $positive,
            'limits.key_length' => $positive,
            'uploads.max_file_size_mb' => $positive,
            'uploads.max_image_size_mb' => $positive,
            'uploads.chunk_size' => $positive,
            'uploads.pending_hours' => $positive,
            'storage_disk' => $string,
            'global_search.enabled' => $bool,
            'global_search.debounce' => fn (mixed $v) => (is_int($v) || is_float($v)) && $v >= 0
                ? null : 'must be a number of seconds, 0 or more; got '.$this->show($v).'. Set it to a number such as 0.5.',
            'global_search.results' => $positive,
            'notifications.enabled' => $bool,
            'notifications.polling_interval' => $positive,
            'notifications.show_unread_count' => $bool,
            'saved_tabs.enabled' => $bool,
            'saved_tabs.max_per_resource' => $positive,
            'keyboard_shortcuts.enabled' => $bool,
            'breadcrumbs.enabled' => $bool,
            'fortify.features' => $this->stringList(),
            'fortify.username' => $nullableString,
            'fortify.email' => $nullableString,
            'impersonation.started' => $string,
            'impersonation.stopped' => $string,
            'action_events.enabled' => $bool,
            'action_events.prune_after_days' => $positive,
            'action_events.prune_per_actionable' => $this->nullable($positive),
            'exports.disk' => $string,
            'exports.directory' => $string,
            'exports.chunk_size' => $positive,
            'exports.prune_after_hours' => $positive,
            'imports.disk' => $string,
            'imports.directory' => $string,
            'imports.chunk_size' => $positive,
            'imports.max_file_size_mb' => $positive,
            'imports.prune_after_hours' => $positive,
            'theme.colors' => function (mixed $v): ?string {
                if (! is_array($v)) {
                    return 'must be an array of colour tokens; got '.$this->show($v).'. Remove it or list tokens such as primary.';
                }

                foreach ($v as $token => $colour) {
                    $valid = $colour === null || (is_string($colour) && ThemeStyleGenerator::isColor($colour))
                        || (is_array($colour) && array_keys($colour) === ['light', 'dark'] && is_string($colour['light']) && is_string($colour['dark'])
                            && ThemeStyleGenerator::isColor($colour['light']) && ThemeStyleGenerator::isColor($colour['dark']));

                    if (! $valid) {
                        return "{$token} must be a CSS colour (oklch(), hsl(), rgb(), hex or a colour name) or ['light' => ..., 'dark' => ...]; got ".$this->show($colour).'. Set a colour such as oklch(0.6 0.2 250).';
                    }
                }

                return null;
            },
            'theme.appearance' => $this->oneOf(['light', 'dark', 'system']),
            'theme.radius' => fn (mixed $v) => is_string($v) && ThemeStyleGenerator::isLength($v)
                ? null : 'must be a CSS length such as 0.625rem; got '.$this->show($v).'. Set it to a length such as 0.625rem.',
            'theme.style' => $this->oneOf(['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera']),
            // A font is {family, fallback, files}: files are woff2 the host serves itself.
            'theme.font' => fn (mixed $v) => $v === null || (is_array($v) && is_string($v['family'] ?? null) && $v['family'] !== ''
                && (! array_key_exists('fallback', $v) || $v['fallback'] === null || is_string($v['fallback']))
                && (! array_key_exists('files', $v) || is_array($v['files'])))
                ? null : 'must be null or an array of family (a string), fallback (a string) and files (a list); got '.$this->show($v).'. Set family to the font name.',
            'layout.sidebar.collapsible' => $this->oneOf(['icon', 'offcanvas', 'none']),
            'layout.sidebar.default_open' => $bool,
            'layout.sidebar.width' => $string,
            'layout.sidebar.width_icon' => $string,
            'layout.header.sticky' => $bool,
            'layout.header.search' => $bool,
            'layout.content.width' => $this->oneOf(['full', 'contained']),
            'table.density' => $this->oneOf(['compact', 'comfortable', 'spacious']),
            'table.striped' => $bool,
        ];
    }

    /** @return Closure(mixed): ?string */
    protected function string(): Closure
    {
        return fn (mixed $v) => is_string($v) && $v !== ''
            ? null : 'must be a non-empty string; got '.$this->show($v).'. Set it to a string.';
    }

    /** @return Closure(mixed): ?string */
    protected function boolean(): Closure
    {
        return fn (mixed $v) => is_bool($v)
            ? null : 'must be true or false; got '.$this->show($v).'. Set it to true or false.';
    }

    /** @return Closure(mixed): ?string */
    protected function integer(int $min): Closure
    {
        return fn (mixed $v) => is_int($v) && $v >= $min
            ? null : "must be an integer of {$min} or more; got ".$this->show($v).". Set it to {$min} or more.";
    }

    /** @return Closure(mixed): ?string */
    protected function stringList(): Closure
    {
        return fn (mixed $v) => is_array($v) && array_is_list($v) && array_filter($v, 'is_string') === $v
            ? null : 'must be a list of strings; got '.$this->show($v).'. Set it to a list of strings.';
    }

    /**
     * @param  list<string>  $allowed
     * @return Closure(mixed): ?string
     */
    protected function oneOf(array $allowed): Closure
    {
        return fn (mixed $v) => in_array($v, $allowed, true)
            ? null : 'must be one of '.implode(', ', $allowed).'; got '.$this->show($v).'. Set it to one of those values.';
    }

    /**
     * @param  Closure(mixed): ?string  $rule
     * @return Closure(mixed): ?string
     */
    protected function nullable(Closure $rule): Closure
    {
        return fn (mixed $v) => $v === null ? null : $rule($v);
    }

    protected function show(mixed $value): string
    {
        return match (true) {
            is_string($value) => "'{$value}'",
            is_int($value), is_float($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
