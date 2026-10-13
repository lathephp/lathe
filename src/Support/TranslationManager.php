<?php

namespace Lathe\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;

/**
 * The translation catalogue, keyed by the English source string. The catalogue of a locale
 * is built once per worker and a request never changes it: Octane runs many requests in one
 * worker, so what a request loads goes to a bag the request drops when it ends.
 */
class TranslationManager
{
    /** The container key of the per-request bag. */
    public const REQUEST_BAG = 'lathe.translations.request';

    /** @var array<string, array<string, string>> */
    protected array $catalogues = [];

    /** @var array<string, string> */
    protected array $merged = [];

    public function __construct(protected string $path)
    {
        //
    }

    /**
     * Merge a JSON file over the catalogue.
     */
    public function load(string $path): static
    {
        return $this->merge(static::read($path));
    }

    /**
     * Merge translations over the catalogue; while a panel request is served, over that request only.
     *
     * @param  array<string, string>  $translations
     */
    public function merge(array $translations): static
    {
        $container = Container::getInstance();

        if ($container->bound(LatheRequest::class)) {
            $container->instance(self::REQUEST_BAG, [...$this->bag(), ...$translations]);

            return $this;
        }

        $this->merged = [...$this->merged, ...$translations];
        $this->catalogues = [];

        return $this;
    }

    public function set(string $key, string $value): static
    {
        return $this->merge([$key => $value]);
    }

    /**
     * Every translation for the locale, the request's own on top.
     *
     * @return array<string, string>
     */
    public function all(?string $locale = null): array
    {
        $locale = static::normalize($locale ?? app()->getLocale());

        return [...($this->catalogues[$locale] ??= $this->build($locale)), ...$this->bag()];
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return array_key_exists($key, $this->all($locale));
    }

    /**
     * The translation, else the default, else the key.
     */
    public function get(string $key, ?string $default = null, ?string $locale = null): string
    {
        return $this->all($locale)[$key] ?? $default ?? $key;
    }

    /**
     * Fill :name as given, :NAME upper-cased and :Name with a capital first letter,
     * the longest name first so :resources never loses to :resource.
     *
     * @param  array<string, scalar|\Stringable|null>  $replace
     */
    public function replace(string $text, array $replace): string
    {
        uksort($replace, fn (string|int $a, string|int $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($replace as $name => $value) {
            $name = (string) $name;
            $value = (string) $value;
            $text = str_replace(
                [':'.$name, ':'.Str::upper($name), ':'.Str::ucfirst($name)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $text,
            );
        }

        return $text;
    }

    /**
     * The locale with - between its parts, such as fr-CA.
     */
    public static function normalize(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    /**
     * Package files, then the app's lang/vendor/lathe files, for English, the language and the locale in turn.
     *
     * @return array<string, string>
     */
    protected function build(string $locale): array
    {
        $chain = array_unique(['en', explode('-', $locale)[0], $locale]);
        $catalogue = [];

        foreach ($chain as $name) {
            $catalogue = [
                ...$catalogue,
                ...static::read("{$this->path}/{$name}.json"),
                ...static::read(lang_path("vendor/lathe/{$name}.json")),
            ];
        }

        return [...$catalogue, ...$this->merged];
    }

    /**
     * @return array<string, string>
     */
    protected function bag(): array
    {
        $container = Container::getInstance();
        $bag = $container->bound(self::REQUEST_BAG) ? $container->make(self::REQUEST_BAG) : [];

        /** @var array<string, string> */
        return is_array($bag) ? $bag : [];
    }

    /**
     * @return array<string, string>
     */
    protected static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        /** @var array<string, string> */
        return is_array($decoded) ? array_filter($decoded, is_string(...)) : [];
    }
}
