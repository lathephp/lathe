<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Lathe\Theme\Contrast;
use Lathe\Theme\ThemeStyleGenerator;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

it('writes colour overrides into one nonced style tag', function () {
    Vite::useCspNonce('n0nce');
    config([
        'lathe.theme.colors.ring' => 'oklch(0.62 0.19 259)',
        'lathe.theme.colors.sidebar' => ['light' => '#f5f5f5', 'dark' => '#171717'],
    ]);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match_all('/<style\b([^>]*)>(.*?)<\/style>/s', $html, $tags);
    expect($tags[1])->toBe([' nonce="n0nce"'])
        ->and(themeVariable($tags[2][0], ':root', 'radius'))->toBe('0.625rem')
        ->and(themeVariable($tags[2][0], ':root', 'ring'))->toBe('oklch(0.62 0.19 259)')
        ->and(themeVariable($tags[2][0], ':root', 'sidebar'))->toBe('#f5f5f5')
        ->and(themeVariable($tags[2][0], '.dark', 'sidebar'))->toBe('#171717');
});

it('keeps a theme value that would close the style tag off the page', function () {
    config([
        'lathe.theme.radius' => '1rem}</style><script>alert(1)</script>',
        'lathe.theme.colors.primary' => 'red}</style><script>alert(1)</script>',
        'lathe.theme.colors.sidebar' => ['light' => '#f5f5f5', 'dark' => '#171717'],
    ]);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match_all('/<style\b[^>]*>(.*?)<\/style>/s', $html, $tags);
    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($tags[1])->toHaveCount(1)
        ->and(themeVariable($tags[1][0], ':root', 'radius'))->toBeNull()
        ->and(themeVariable($tags[1][0], ':root', 'primary'))->toBe('oklch(0.205 0 0)')
        ->and(themeVariable($tags[1][0], ':root', 'sidebar'))->toBe('#f5f5f5');
});

it('drops every theme value that is not a CSS colour or length', function () {
    $css = (new ThemeStyleGenerator)->generate('1rem;color:red', [
        'primary' => 'red;x',
        'ring' => 'oklch(0.5 0.1 200 / 50%)',
        'accent' => 'RebeccaPurple',
        'muted' => 'hsl(210 40% 50%)',
        'sidebar' => ['light' => '#fff', 'dark' => 'blue}'],
        'border' => 'url(x)',
        'input' => 'rgb(1 2 3){',
    ]);

    // The dropped values fall back to the defaults before copper, as any unset token does.
    expect(themeVariable($css, ':root', 'radius'))->toBeNull()
        ->and(themeVariable($css, ':root', 'muted'))->toBe('hsl(210 40% 50%)')
        ->and(themeVariable($css, ':root', 'accent'))->toBe('RebeccaPurple')
        ->and(themeVariable($css, ':root', 'ring'))->toBe('oklch(0.5 0.1 200 / 50%)')
        ->and(themeVariable($css, ':root', 'skeleton'))->toBe('hsl(210 40% 50%)')
        ->and(themeVariable($css, ':root', 'primary'))->toBe('oklch(0.205 0 0)')
        ->and(themeVariable($css, ':root', 'sidebar'))->toBe('oklch(0.985 0 0)')
        ->and(themeVariable($css, ':root', 'border'))->toBe('oklch(0.922 0 0)')
        ->and(themeVariable($css, ':root', 'input'))->toBe('oklch(0.922 0 0)')
        ->and($css)->not->toContain('red;x')->not->toContain('url(x)')->not->toContain('blue}')->not->toContain('{;');
});

/** The value of a variable in the :root or .dark block of generated CSS. */
function themeVariable(string $css, string $block, string $name): ?string
{
    preg_match('/'.preg_quote($block, '/').'\{([^}]*)\}/', $css, $body);
    preg_match('/(?:^|;)--'.preg_quote($name, '/').':([^;]+)/', $body[1] ?? '', $match);

    return $match[1] ?? null;
}

it('derives the new tokens from an app primary and keeps the rest of its look', function () {
    $css = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => '#2563eb']);

    // An app with its own colours wears no copper: the tokens it left unset keep their values
    // from before copper, the white and near-black of shadcn's neutral.
    expect(themeVariable($css, ':root', 'primary'))->toBe('#2563eb')
        ->and(themeVariable($css, ':root', 'brand'))->toBe('#2563eb')
        ->and(themeVariable($css, ':root', 'focus'))->toBe('#2563eb')
        ->and(themeVariable($css, ':root', 'ring'))->toBe('oklch(0.64 0 0)')
        ->and(themeVariable($css, '.dark', 'ring'))->toBe('oklch(0.556 0 0)')
        ->and(themeVariable($css, ':root', 'chart-1'))->toBe('oklch(0.646 0.222 41.116)')
        ->and(themeVariable($css, ':root', 'link'))->toBe('oklch(0.488 0.243 264.376)')
        ->and(themeVariable($css, '.dark', 'link'))->toBe('oklch(0.707 0.165 254.624)')
        ->and(themeVariable($css, ':root', 'sidebar-accent'))->toBe('oklch(0.97 0 0)')
        ->and(themeVariable($css, ':root', 'sidebar-hover'))->toBe('oklch(0.97 0 0)')
        ->and(themeVariable($css, ':root', 'background'))->toBe('oklch(1 0 0)')
        ->and(themeVariable($css, ':root', 'primary-subtle'))->toBe('color-mix(in oklch, #2563eb 14%, var(--background))');

    // The tints, worked out by hand: color-mix(in oklch, #2563eb 14%, <background>) on white
    // and on oklch(0.145 0 0). A single colour shows in dark mode too, so the text gets a dark value.
    expect(themeVariable($css, '.dark', 'primary'))->toBeNull()
        ->and(Contrast::ratio((string) themeVariable($css, ':root', 'primary-subtle-foreground'), 'oklch(0.9365 0.0301 262.88)'))->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio((string) themeVariable($css, '.dark', 'primary-subtle-foreground'), 'oklch(0.2012 0.0301 262.88)'))->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio((string) themeVariable($css, '.dark', 'brand'), 'oklch(0.145 0 0)'))->toBeGreaterThanOrEqual(3.0);

    $light = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => '#60a5fa']);

    expect(Contrast::ratio('#60a5fa', 'oklch(0.9599 0.0201 254.62)'))->toBeLessThan(4.5)
        ->and(Contrast::ratio((string) themeVariable($light, ':root', 'primary-subtle-foreground'), 'oklch(0.9599 0.0201 254.62)'))->toBeGreaterThanOrEqual(4.5);
});

it('keeps a new token the app set over the derived one', function () {
    $css = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => '#2563eb', 'brand' => '#ff0000', 'link' => '#00ff00']);

    expect(themeVariable($css, ':root', 'brand'))->toBe('#ff0000')
        ->and(themeVariable($css, ':root', 'focus'))->toBe('#2563eb')
        ->and(themeVariable($css, ':root', 'link'))->toBe('#00ff00')
        ->and(themeVariable($css, ':root', 'link-hover'))->toBe('oklch(0.424 0.199 265.638)');
});

it('writes nothing but the radius for an app without colours, so app.css supplies copper', function () {
    expect((new ThemeStyleGenerator)->generate('0.625rem', []))->toBe(':root{--radius:0.625rem}');

    $border = (new ThemeStyleGenerator)->generate('0.625rem', ['border' => '#cccccc']);

    expect(themeVariable($border, ':root', 'border'))->toBe('#cccccc')
        ->and(themeVariable($border, ':root', 'primary'))->toBe('oklch(0.205 0 0)')
        ->and(themeVariable($border, '.dark', 'primary'))->toBe('oklch(0.922 0 0)');
});

it('derives a light and a dark value from a light and dark primary, each readable as a mark', function () {
    $css = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => ['light' => '#60a5fa', 'dark' => '#2563eb']]);

    // #60a5fa reads 2.5:1 on white, under the 3:1 a mark needs, so the brand bar darkens it.
    expect(Contrast::ratio('#60a5fa', 'oklch(1 0 0)'))->toBeLessThan(3.0)
        ->and(Contrast::ratio((string) themeVariable($css, ':root', 'brand'), 'oklch(1 0 0)'))->toBeGreaterThanOrEqual(3.0)
        ->and(themeVariable($css, '.dark', 'brand'))->toBe('#2563eb')
        ->and(themeVariable($css, '.dark', 'primary-subtle'))->toBe('color-mix(in oklch, #2563eb 14%, var(--background))')
        ->and(Contrast::ratio((string) themeVariable($css, '.dark', 'primary-subtle-foreground'), 'oklch(0.2012 0.0301 262.88)'))->toBeGreaterThanOrEqual(4.5);
});

it('keeps a preset app link and makes its brand readable in dark mode', function () {
    // The violet preset of PresetCommandTest: its dark primary reads 2.16:1 on its dark background.
    $css = (new ThemeStyleGenerator)->generate('0.625rem', [
        'primary' => ['light' => 'oklch(0.541 0.281 293.009)', 'dark' => 'oklch(0.432 0.232 292.759)'],
        'background' => ['light' => 'oklch(1 0 0)', 'dark' => 'oklch(0.145 0 0)'],
    ]);

    expect(Contrast::ratio('oklch(0.432 0.232 292.759)', 'oklch(0.145 0 0)'))->toBeLessThan(3.0)
        ->and(Contrast::ratio((string) themeVariable($css, '.dark', 'brand'), 'oklch(0.145 0 0)'))->toBeGreaterThanOrEqual(3.0)
        ->and(themeVariable($css, '.dark', 'link'))->toBe('oklch(0.707 0.165 254.624)');
});

it('writes the derived tokens into the page style tag', function () {
    config(['lathe.theme.colors.primary' => '#2563eb']);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match('/<style\b[^>]*>(.*?)<\/style>/s', $html, $tag);
    expect(themeVariable($tag[1], ':root', 'brand'))->toBe('#2563eb')
        ->and(themeVariable($tag[1], ':root', 'focus'))->toBe('#2563eb')
        ->and(themeVariable($tag[1], ':root', 'primary-subtle'))->toBe('color-mix(in oklch, #2563eb 14%, var(--background))');
});
