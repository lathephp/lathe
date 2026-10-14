<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lathe\Lathe;
use Lathe\Theme\Color;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\select;

#[AsCommand(name: 'lathe:preset')]
class PresetCommand extends Command
{
    protected $signature = 'lathe:preset
        {preset? : A shadcn preset code, such as a5ONTE}
        {--interactive : Choose each preset parameter in turn}
        {--show : Print the theme in config/lathe.php without changing it}
        {--reset : Set every colour override to null}';

    protected $description = 'Apply a shadcn preset to the theme keys of config/lathe.php';

    /** Radius names as shadcn spells them, and the CSS length each one writes. */
    private const RADII = [
        'default' => '0.625rem',
        'none' => '0',
        'small' => '0.45rem',
        'medium' => '0.625rem',
        'large' => '0.875rem',
    ];

    /** Styles that force the radius to "none" whatever the preset says. */
    private const RADIUS_LOCKED_STYLES = ['lyra'];

    private const STYLES = ['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'sera', 'rhea'];

    private const FONT_FALLBACK = 'ui-sans-serif, system-ui, sans-serif';

    private const BASE_COLORS = ['neutral', 'stone', 'zinc', 'mauve', 'olive', 'mist', 'taupe'];

    public function handle(): int
    {
        $path = config_path('lathe.php');

        if (! is_file($path)) {
            $this->components->error(Lathe::__('config/lathe.php is not published. Run php artisan lathe:install first.'));

            return self::FAILURE;
        }

        $theme = $this->currentTheme($path);

        if ($this->option('show')) {
            return $this->show($theme);
        }

        if ($this->option('reset')) {
            $this->write($path, $this->withColors($theme, []));
            $this->components->info(Lathe::__('Colour overrides cleared.'));

            return self::SUCCESS;
        }

        $params = $this->option('interactive') ? $this->askParams() : $this->codeParams();

        if ($params === null) {
            return self::FAILURE;
        }

        return $this->apply($path, $theme, $params);
    }

    /**
     * @param  array<mixed>  $theme
     * @param  array<string, string>  $params
     */
    private function apply(string $path, array $theme, array $params): int
    {
        $response = Http::timeout(15)->get('https://ui.shadcn.com/init', $params);

        if ($response->failed()) {
            $body = $response->json();
            $message = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;

            $this->components->error(Lathe::__('shadcn answered :status for that preset.', ['status' => $response->status()]));

            if (is_string($message) && $message !== '') {
                $this->components->bulletList([$message]);
            }

            return self::FAILURE;
        }

        $data = $response->json();

        $cssVars = is_array($data) ? ($data['cssVars'] ?? null) : null;

        if (! is_array($data) || ! is_array($cssVars) || ! is_array($cssVars['light'] ?? null) || ! is_array($cssVars['dark'] ?? null)) {
            $this->components->error(Lathe::__('The preset response has no light and dark colours.'));

            return self::FAILURE;
        }

        $decoded = isset($params['preset']) ? self::decode($params['preset']) : $params;
        $style = $this->style($decoded, $data);
        $radius = $this->radius($decoded, $style);
        $font = $this->font($data);

        $theme = $this->withColors($theme, $this->colors($cssVars['light'], $cssVars['dark']));
        $theme['style'] = $style;
        $theme['radius'] = $radius;
        $theme['font'] = $font;

        $this->write($path, $theme);
        $this->components->info(Lathe::__('Preset applied to config/lathe.php.'));

        if ($font !== null) {
            $this->components->warn(Lathe::__('Add font files for :family to theme.font.files in config/lathe.php, or the panel uses the fallback stack.', ['family' => $font['family']]));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>|null
     */
    private function codeParams(): ?array
    {
        $code = $this->argument('preset');

        if (! is_string($code) || $code === '') {
            $this->components->error(Lathe::__('Pass a preset code, such as a5ONTE, or use --interactive. Missing argument: preset.'));

            return null;
        }

        if (self::decode($code) === null) {
            $this->components->error(Lathe::__(':code is not a shadcn preset code.', ['code' => $code]));

            return null;
        }

        return ['preset' => $code];
    }

    /**
     * @return array<string, string>
     */
    private function askParams(): array
    {
        // The API needs these three, and they do not change the colours Lathe reads.
        $params = ['base' => 'base', 'iconLibrary' => 'lucide', 'menuColor' => 'default'];

        $params['style'] = $this->choose(Lathe::__('Style'), self::STYLES, 'nova');
        $params['baseColor'] = $this->choose(Lathe::__('Base Color'), self::BASE_COLORS, 'zinc');
        $params['theme'] = $this->choose(Lathe::__('Theme Color'), array_values(array_filter(
            [...self::BASE_COLORS, 'amber', 'blue', 'cyan', 'emerald', 'fuchsia', 'green', 'indigo', 'lime', 'orange', 'pink', 'purple', 'red', 'rose', 'sky', 'teal', 'violet', 'yellow'],
            fn (string $theme) => $theme === $params['baseColor'] || ! in_array($theme, self::BASE_COLORS, true),
        )), 'blue');
        $params['font'] = $this->choose(Lathe::__('Font'), [
            'geist', 'inter', 'noto-sans', 'nunito-sans', 'figtree', 'roboto',
            'raleway', 'dm-sans', 'public-sans', 'outfit', 'jetbrains-mono',
            'geist-mono', 'noto-serif', 'roboto-slab', 'merriweather', 'lora',
            'playfair-display', 'oxanium', 'manrope', 'space-grotesk',
            'montserrat', 'ibm-plex-sans', 'source-sans-3', 'instrument-sans',
            'eb-garamond', 'instrument-serif',
        ], 'inter');
        $params['radius'] = $this->choose(Lathe::__('Radius'), ['none', 'small', 'default', 'medium', 'large'], 'default');
        $params['menuAccent'] = $this->choose(Lathe::__('Menu Accent'), ['subtle', 'bold'], 'subtle');

        return $params;
    }

    /**
     * @param  list<string>  $options
     */
    private function choose(string $label, array $options, string $default): string
    {
        return (string) select(label: $label, options: $options, default: $default);
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @param  array<mixed>  $data
     */
    private function style(?array $decoded, array $data): string
    {
        $style = $decoded['style'] ?? null;

        if (! is_string($style)) {
            // The response names it as "base-nova".
            $config = $data['config'] ?? null;
            $raw = is_array($config) ? ($config['style'] ?? '') : '';
            $style = is_string($raw) ? substr($raw, (int) strpos($raw, '-') + 1) : '';
        }

        return in_array($style, self::STYLES, true) ? $style : 'nova';
    }

    /**
     * The API returns the base theme's radius whatever the preset chose, so the radius
     * comes from the code or the answers, and a locked style wins over both.
     *
     * @param  array<string, mixed>|null  $decoded
     */
    private function radius(?array $decoded, string $style): string
    {
        $chosen = $decoded['radius'] ?? null;
        $name = in_array($style, self::RADIUS_LOCKED_STYLES, true) ? 'none' : (is_string($chosen) ? $chosen : 'default');

        return self::RADII[$name] ?? self::RADII['default'];
    }

    /**
     * @param  array<mixed>  $data
     * @return array{family: string, fallback: string, files: array<never, never>}|null
     */
    private function font(array $data): ?array
    {
        foreach ((array) ($data['registryDependencies'] ?? []) as $dependency) {
            if (is_string($dependency) && str_starts_with($dependency, 'font-')) {
                $family = ucwords(str_replace('-', ' ', substr($dependency, 5)));

                return ['family' => $family, 'fallback' => self::FONT_FALLBACK, 'files' => []];
            }
        }

        return null;
    }

    /**
     * The tokens the preset names, as {light, dark}. The rest stay null: shadcn has no
     * destructive-foreground, success, warning, info or link token.
     *
     * @param  array<mixed>  $lightVars
     * @param  array<mixed>  $darkVars
     * @return array<string, array{light: string, dark: string}|string>
     */
    private function colors(array $lightVars, array $darkVars): array
    {
        $colors = [];

        foreach (Color::cases() as $color) {
            $light = $lightVars[$color->value] ?? null;
            $dark = $darkVars[$color->value] ?? null;

            if (! is_string($light) && ! is_string($dark)) {
                continue;
            }

            $light = is_string($light) ? $light : $dark;
            $dark = is_string($dark) ? $dark : $light;
            $colors[$color->value] = $light === $dark ? $light : ['light' => $light, 'dark' => $dark];
        }

        return $colors;
    }

    /**
     * @param  array<mixed>  $theme
     * @param  array<string, mixed>  $colors
     * @return array<mixed>
     */
    private function withColors(array $theme, array $colors): array
    {
        $theme['colors'] = [];

        foreach (Color::cases() as $color) {
            $theme['colors'][$color->value] = $colors[$color->value] ?? null;
        }

        return $theme;
    }

    /**
     * @return array<mixed>
     */
    private function currentTheme(string $path): array
    {
        $config = (static fn () => require $path)();
        $theme = is_array($config) ? ($config['theme'] ?? []) : [];

        return is_array($theme) ? $theme : [];
    }

    /**
     * @param  array<mixed>  $theme
     */
    private function show(array $theme): int
    {
        $font = $theme['font'] ?? null;
        $colors = array_filter((array) ($theme['colors'] ?? []), fn (mixed $value) => $value !== null);

        $lines = [
            'Style: '.$this->scalar($theme['style'] ?? null),
            'Appearance: '.$this->scalar($theme['appearance'] ?? null),
            'Radius: '.$this->scalar($theme['radius'] ?? null),
            'Font: '.(is_array($font) ? $this->scalar($font['family'] ?? null) : $this->scalar($font)),
            'Colours: '.count($colors).' overridden',
            ...array_map(fn (string $token, mixed $value) => "  {$token}: ".(is_array($value) ? $this->scalar($value['light'] ?? null).' / '.$this->scalar($value['dark'] ?? null) : $this->scalar($value)), array_keys($colors), $colors),
        ];

        foreach ($lines as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : 'default';
    }

    /**
     * Replace the theme block of the config file, leaving the rest of the file as it is.
     *
     * @param  array<mixed>  $theme
     */
    private function write(string $path, array $theme): void
    {
        $lines = ["    'theme' => [", "        'colors' => ["];

        foreach ((array) $theme['colors'] as $token => $value) {
            $lines[] = "            '{$token}' => ".$this->export($value).',';
        }

        $lines[] = '        ],';

        foreach (['appearance', 'radius', 'style', 'font'] as $key) {
            $lines[] = "        '{$key}' => ".$this->export($theme[$key] ?? null).',';
        }

        $lines[] = '    ],';
        $block = implode("\n", $lines);

        $contents = (string) file_get_contents($path);
        $replaced = preg_replace_callback("/    'theme'\s*=>\s*\[.*?\n    \],/s", fn () => $block, $contents, 1, $count);

        if ($count !== 1 || $replaced === null) {
            throw new \RuntimeException('config/lathe.php has no theme block to rewrite.');
        }

        file_put_contents($path, $replaced);
    }

    private function export(mixed $value): string
    {
        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $items[] = (is_string($key) ? $this->export($key).' => ' : '').$this->export($item);
            }

            return '['.implode(', ', $items).']';
        }

        if (is_string($value)) {
            return "'".addcslashes($value, "'\\")."'";
        }

        return var_export($value, true);
    }

    /**
     * Decode a shadcn preset code: a version letter and a base62 number that packs the
     * design parameters in bit fields. The fields must match shadcn's preset.ts in order.
     *
     * @return array<string, string>|null
     */
    private static function decode(string $code): ?array
    {
        if (strlen($code) < 2 || ! in_array($code[0], ['a', 'b'], true)) {
            return null;
        }

        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $number = 0;

        foreach (str_split(substr($code, 1)) as $char) {
            $digit = strpos($alphabet, $char);

            if ($digit === false) {
                return null;
            }

            $number = $number * 62 + $digit;
        }

        $result = [];
        $offset = 0;

        foreach (self::fields($code[0]) as $field) {
            $index = (int) (floor($number / (2 ** $offset)) % (2 ** $field['bits']));
            $result[$field['key']] = $field['values'][$index] ?? $field['values'][0];
            $offset += $field['bits'];
        }

        return $result;
    }

    /**
     * @return list<array{key: string, values: list<string>, bits: int}>
     */
    private static function fields(string $version): array
    {
        $fonts = [
            'inter', 'noto-sans', 'nunito-sans', 'figtree', 'roboto', 'raleway',
            'dm-sans', 'public-sans', 'outfit', 'jetbrains-mono', 'geist',
            'geist-mono', 'lora', 'merriweather', 'playfair-display', 'noto-serif',
            'roboto-slab', 'oxanium', 'manrope', 'space-grotesk', 'montserrat',
            'ibm-plex-sans', 'source-sans-3', 'instrument-sans', 'eb-garamond',
            'instrument-serif',
        ];

        $themes = [
            'neutral', 'stone', 'zinc', 'gray', 'amber', 'blue', 'cyan', 'emerald',
            'fuchsia', 'green', 'indigo', 'lime', 'orange', 'pink', 'purple', 'red',
            'rose', 'sky', 'teal', 'violet', 'yellow', 'mauve', 'olive', 'mist', 'taupe',
        ];

        $fields = [
            ['key' => 'menuColor', 'values' => ['default', 'inverted', 'default-translucent', 'inverted-translucent'], 'bits' => 3],
            ['key' => 'menuAccent', 'values' => ['subtle', 'bold'], 'bits' => 3],
            ['key' => 'radius', 'values' => ['default', 'none', 'small', 'medium', 'large'], 'bits' => 4],
            ['key' => 'font', 'values' => $fonts, 'bits' => 6],
            ['key' => 'iconLibrary', 'values' => ['lucide', 'hugeicons', 'tabler', 'phosphor', 'remixicon'], 'bits' => 6],
            ['key' => 'theme', 'values' => $themes, 'bits' => 6],
            ['key' => 'baseColor', 'values' => ['neutral', 'stone', 'zinc', 'gray', 'mauve', 'olive', 'mist', 'taupe'], 'bits' => 6],
            ['key' => 'style', 'values' => ['nova', 'vega', 'maia', 'lyra', 'mira', 'luma', 'sera', 'rhea'], 'bits' => 6],
        ];

        if ($version === 'b') {
            $fields[] = ['key' => 'chartColor', 'values' => $themes, 'bits' => 6];
            $fields[] = ['key' => 'fontHeading', 'values' => ['inherit', ...$fonts], 'bits' => 5];
        }

        return $fields;
    }
}
