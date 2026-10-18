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

    preg_match_all('/<style\b[^>]*>.*?<\/style>/s', $html, $tags);
    expect($tags[0])->toBe([
        '<style nonce="n0nce">:root{--radius:0.625rem;--ring:oklch(0.62 0.19 259);--sidebar:#f5f5f5}.dark{--sidebar:#171717}</style>',
    ]);
});

it('keeps a theme value that would close the style tag off the page', function () {
    config([
        'lathe.theme.radius' => '1rem}</style><script>alert(1)</script>',
        'lathe.theme.colors.primary' => 'red}</style><script>alert(1)</script>',
        'lathe.theme.colors.sidebar' => ['light' => '#f5f5f5', 'dark' => '#171717'],
    ]);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match_all('/<style\b[^>]*>.*?<\/style>/s', $html, $tags);
    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($tags[0])->toBe(['<style>:root{--sidebar:#f5f5f5}.dark{--sidebar:#171717}</style>']);
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

    expect($css)->toBe(':root{--muted:hsl(210 40% 50%);--accent:RebeccaPurple;--ring:oklch(0.5 0.1 200 / 50%);--skeleton:hsl(210 40% 50%);--sidebar-hover:RebeccaPurple}');
});

/** The value of a variable in the :root or .dark block of generated CSS. */
function themeVariable(string $css, string $block, string $name): ?string
{
    preg_match('/'.preg_quote($block, '/').'\{([^}]*)\}/', $css, $body);
    preg_match('/(?:^|;)--'.preg_quote($name, '/').':([^;]+)/', $body[1] ?? '', $match);

    return $match[1] ?? null;
}

it('derives the new tokens from an app primary', function () {
    $css = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => '#2563eb']);

    // The tints are color-mix(in oklch, <primary> 14%, <copper background oklch(0.985 0.003 70)>), worked out by hand.
    expect($css)->toStartWith(':root{--radius:0.625rem;--primary:#2563eb;--link:#2563eb;--link-hover:color-mix(in oklch, #2563eb 85%, black);--brand:#2563eb;--focus:#2563eb;--primary-subtle:color-mix(in oklch, #2563eb 14%, var(--background));--primary-subtle-foreground:')
        ->and(Contrast::ratio((string) themeVariable($css, ':root', 'primary-subtle-foreground'), 'oklch(0.9236 0.0327 46.60)'))->toBeGreaterThanOrEqual(4.5);

    // The tint follows the dark background, so the text gets a dark value of its own:
    // the tint there is color-mix(in oklch, #2563eb 14%, oklch(0.165 0.006 60)).
    expect(themeVariable($css, '.dark', 'primary'))->toBeNull()
        ->and(Contrast::ratio((string) themeVariable($css, '.dark', 'primary-subtle-foreground'), 'oklch(0.2184 0.0353 38.00)'))->toBeGreaterThanOrEqual(4.5);

    $light = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => '#60a5fa']);

    expect(Contrast::ratio('#60a5fa', 'oklch(0.9470 0.0227 45.45)'))->toBeLessThan(4.5)
        ->and(Contrast::ratio((string) themeVariable($light, ':root', 'primary-subtle-foreground'), 'oklch(0.9470 0.0227 45.45)'))->toBeGreaterThanOrEqual(4.5);
});

it('keeps a new token the app set over the derived one', function () {
    $css = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => '#2563eb', 'brand' => '#ff0000', 'link' => '#00ff00']);

    expect(themeVariable($css, ':root', 'brand'))->toBe('#ff0000')
        ->and(themeVariable($css, ':root', 'focus'))->toBe('#2563eb')
        ->and(themeVariable($css, ':root', 'link'))->toBe('#00ff00')
        ->and(themeVariable($css, ':root', 'link-hover'))->toBeNull();
});

it('derives nothing for an app without colours, so app.css supplies copper', function () {
    expect((new ThemeStyleGenerator)->generate('0.625rem', []))->toBe(':root{--radius:0.625rem}')
        ->and((new ThemeStyleGenerator)->generate('0.625rem', ['border' => '#cccccc']))->toBe(':root{--radius:0.625rem;--border:#cccccc}');
});

it('derives a light and a dark value from a light and dark primary', function () {
    $css = (new ThemeStyleGenerator)->generate('0.625rem', ['primary' => ['light' => '#60a5fa', 'dark' => '#2563eb']]);

    // The dark tint mixes into the copper dark background oklch(0.165 0.006 60).
    expect(themeVariable($css, ':root', 'brand'))->toBe('#60a5fa')
        ->and(themeVariable($css, '.dark', 'brand'))->toBe('#2563eb')
        ->and(themeVariable($css, '.dark', 'primary-subtle'))->toBe('color-mix(in oklch, #2563eb 14%, var(--background))')
        ->and(Contrast::ratio('#2563eb', 'oklch(0.2184 0.0353 38.00)'))->toBeLessThan(4.5)
        ->and(Contrast::ratio((string) themeVariable($css, '.dark', 'primary-subtle-foreground'), 'oklch(0.2184 0.0353 38.00)'))->toBeGreaterThanOrEqual(4.5);
});

it('writes the derived tokens into the page style tag', function () {
    config(['lathe.theme.colors.primary' => '#2563eb']);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match('/<style\b[^>]*>(.*?)<\/style>/s', $html, $tag);
    expect(themeVariable($tag[1], ':root', 'brand'))->toBe('#2563eb')
        ->and(themeVariable($tag[1], ':root', 'focus'))->toBe('#2563eb')
        ->and(themeVariable($tag[1], ':root', 'primary-subtle'))->toBe('color-mix(in oklch, #2563eb 14%, var(--background))');
});
