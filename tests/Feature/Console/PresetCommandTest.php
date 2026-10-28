<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->configDir = sys_get_temp_dir().'/lathe-preset-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->configDir);
    app()->useConfigPath($this->configDir);
    $this->configPath = config_path('lathe.php');
    File::copy(dirname(__DIR__, 3).'/config/lathe.php', $this->configPath);
});

afterEach(function () {
    File::deleteDirectory($this->configDir);
});

/** @return array<string, mixed> */
function presetFixture(string $name): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2)."/Fixtures/shadcn/{$name}.json"), true);
}

/** The theme array of the config file the command rewrote. */
function writtenTheme(string $path): array
{
    return (require $path)['theme'];
}

it('applies a preset code to the theme keys and sends one request', function () {
    Http::fake(['ui.shadcn.com/init*' => Http::response(presetFixture('preset-a5ONTE'))]);

    $this->artisan('lathe:preset', ['preset' => 'a5ONTE'])
        ->expectsOutputToContain('font files')
        ->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://ui.shadcn.com/init?preset=a5ONTE');

    $theme = writtenTheme($this->configPath);

    expect($theme['style'])->toBe('nova')
        ->and($theme['radius'])->toBe('0.625rem')
        ->and($theme['appearance'])->toBe('system')
        ->and($theme['font'])->toBe(['family' => 'Inter', 'fallback' => 'ui-sans-serif, system-ui, sans-serif', 'files' => []])
        ->and($theme['colors'])->toHaveCount(56)
        ->and($theme['colors']['primary'])->toBe(['light' => 'oklch(0.491 0.27 292.581)', 'dark' => 'oklch(0.432 0.232 292.759)'])
        ->and($theme['colors']['background'])->toBe(['light' => 'oklch(1 0 0)', 'dark' => 'oklch(0.145 0 0)'])
        ->and($theme['colors']['sidebar-ring'])->toBe(['light' => 'oklch(0.708 0 0)', 'dark' => 'oklch(0.556 0 0)'])
        ->and($theme['colors']['success'])->toBeNull()
        ->and(count(array_filter($theme['colors'])))->toBe(31);
});

it('asks once per parameter with --interactive and locks the lyra radius', function () {
    Http::fake(['ui.shadcn.com/init*' => Http::response(presetFixture('preset-lyra'))]);

    $this->artisan('lathe:preset', ['--interactive' => true])
        ->expectsChoice('Style', 'lyra', ['vega', 'nova', 'maia', 'lyra', 'mira', 'luma', 'sera', 'rhea'])
        ->expectsChoice('Base Color', 'zinc', ['neutral', 'stone', 'zinc', 'mauve', 'olive', 'mist', 'taupe'])
        ->expectsChoice('Theme Color', 'blue', ['zinc', 'amber', 'blue', 'cyan', 'emerald', 'fuchsia', 'green', 'indigo', 'lime', 'orange', 'pink', 'purple', 'red', 'rose', 'sky', 'teal', 'violet', 'yellow'])
        ->expectsChoice('Font', 'inter', [
            'geist', 'inter', 'noto-sans', 'nunito-sans', 'figtree', 'roboto',
            'raleway', 'dm-sans', 'public-sans', 'outfit', 'jetbrains-mono',
            'geist-mono', 'noto-serif', 'roboto-slab', 'merriweather', 'lora',
            'playfair-display', 'oxanium', 'manrope', 'space-grotesk',
            'montserrat', 'ibm-plex-sans', 'source-sans-3', 'instrument-sans',
            'eb-garamond', 'instrument-serif',
        ])
        ->expectsChoice('Radius', 'medium', ['none', 'small', 'default', 'medium', 'large'])
        ->expectsChoice('Menu Accent', 'subtle', ['subtle', 'bold'])
        ->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://ui.shadcn.com/init?base=base&iconLibrary=lucide&menuColor=default&style=lyra&baseColor=zinc&theme=blue&font=inter&radius=medium&menuAccent=subtle');

    $theme = writtenTheme($this->configPath);

    expect($theme['style'])->toBe('lyra')
        ->and($theme['radius'])->toBe('0')
        ->and($theme['colors']['primary'])->toBe(['light' => 'oklch(0.488 0.243 264.376)', 'dark' => 'oklch(0.424 0.199 265.638)']);
});

it('shows the theme and makes no request', function () {
    $this->artisan('lathe:preset', ['--show' => true])
        ->expectsOutputToContain('Style: nova')
        ->expectsOutputToContain('Radius: 0.5rem')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('resets every colour to null and keeps style, radius and font', function () {
    Http::fake(['ui.shadcn.com/init*' => Http::response(presetFixture('preset-a5ONTE'))]);
    $this->artisan('lathe:preset', ['preset' => 'a5ONTE'])->assertExitCode(0);

    $this->artisan('lathe:preset', ['--reset' => true])->assertExitCode(0);

    $theme = writtenTheme($this->configPath);

    expect($theme['colors'])->toHaveCount(56)
        ->and(array_filter($theme['colors']))->toBe([])
        ->and($theme['style'])->toBe('nova')
        ->and($theme['radius'])->toBe('0.625rem')
        ->and($theme['font'])->toBe(['family' => 'Inter', 'fallback' => 'ui-sans-serif, system-ui, sans-serif', 'files' => []]);
    Http::assertSentCount(1);
});

it('names the preset argument when none is given without a terminal', function () {
    $this->artisan('lathe:preset', ['--no-interaction' => true])
        ->expectsOutputToContain('preset')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('fails on a code the API refuses', function () {
    Http::fake(['ui.shadcn.com/init*' => Http::response(['error' => 'Preset not found'], 404)]);

    $before = file_get_contents($this->configPath);

    $this->artisan('lathe:preset', ['preset' => 'aZZZZZ'])
        ->expectsOutputToContain('Preset not found')
        ->assertExitCode(1);

    expect(file_get_contents($this->configPath))->toBe($before);
});

it('refuses a preset whose colour is not a CSS colour and leaves the config alone', function () {
    $poisoned = presetFixture('preset-a5ONTE');
    $poisoned['cssVars']['dark']['primary'] = 'red}</style><script>alert(1)</script>';
    Http::fake(['ui.shadcn.com/init*' => Http::response($poisoned)]);

    $before = file_get_contents($this->configPath);

    $this->artisan('lathe:preset', ['preset' => 'a5ONTE'])
        ->expectsOutputToContain('primary')
        ->assertExitCode(1);

    expect(file_get_contents($this->configPath))->toBe($before);
});

it('fails on a code that does not decode without a request', function () {
    $this->artisan('lathe:preset', ['preset' => 'not a code'])->assertExitCode(1);

    Http::assertNothingSent();
});

it('fails when the config is not published', function () {
    File::delete($this->configPath);

    $this->artisan('lathe:preset', ['preset' => 'a5ONTE'])
        ->expectsOutputToContain('lathe:install')
        ->assertExitCode(1);

    Http::assertNothingSent();
});
