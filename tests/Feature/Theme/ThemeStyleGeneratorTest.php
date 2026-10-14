<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        'lathe.theme.colors.primary' => 'oklch(0.62 0.19 259)',
        'lathe.theme.colors.sidebar' => ['light' => '#f5f5f5', 'dark' => '#171717'],
    ]);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match_all('/<style\b[^>]*>.*?<\/style>/s', $html, $tags);
    expect($tags[0])->toBe([
        '<style nonce="n0nce">:root{--radius:0.625rem;--primary:oklch(0.62 0.19 259);--sidebar:#f5f5f5}.dark{--sidebar:#171717}</style>',
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

    expect($css)->toBe(':root{--muted:hsl(210 40% 50%);--accent:RebeccaPurple;--ring:oklch(0.5 0.1 200 / 50%)}');
});
