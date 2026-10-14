<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Lathe\Lathe;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->dist = sys_get_temp_dir().'/lathe-font-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    File::put("{$this->dist}/assets/inter-latin-wght-normal-fo0nt123.woff2", 'woff2-bytes');
    File::put("{$this->dist}/manifest.json", json_encode([
        'resources/js/app.tsx' => ['file' => 'assets/app-abcd1234.js', 'isEntry' => true, 'css' => ['assets/app-ffff0000.css']],
        'resources/fonts/inter-latin-wght-normal.woff2' => ['file' => 'assets/inter-latin-wght-normal-fo0nt123.woff2'],
    ]));
    Lathe::assets()->useDistPath($this->dist);

    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

afterEach(fn () => File::deleteDirectory($this->dist));

function fontPageSource(): string
{
    return (string) test()->actingAs(test()->admin)->get('/lathe')->getContent();
}

it('preloads the bundled font from the panel assets with the nonce', function () {
    Vite::useCspNonce('n0nce');

    $html = fontPageSource();

    preg_match_all('/<link rel="preload"[^>]*>/', $html, $links);
    expect($links[0])->toBe(['<link rel="preload" as="font" type="font/woff2" href="/lathe/assets/abcd1234/inter-latin-wght-normal-fo0nt123.woff2" crossorigin nonce="n0nce">']);
});

it('serves the bundled font as woff2', function () {
    $this->actingAs($this->admin)
        ->get('/lathe/assets/abcd1234/inter-latin-wght-normal-fo0nt123.woff2')
        ->assertOk()
        ->assertHeader('Content-Type', 'font/woff2');
});

it('writes the host font rules, preloads its first file and sets --font-sans', function () {
    Vite::useCspNonce('n0nce');
    config(['lathe.theme.font' => [
        'family' => 'Acme Sans',
        'fallback' => 'sans-serif',
        'files' => [
            ['url' => '/fonts/acme-regular.woff2', 'weight' => 400, 'style' => 'normal'],
            ['url' => '/fonts/acme-bold.woff2', 'weight' => 700, 'style' => 'italic'],
        ],
    ]]);

    $html = fontPageSource();

    preg_match_all('/<link rel="preload"[^>]*>/', $html, $links);
    expect($links[0])->toBe(['<link rel="preload" as="font" type="font/woff2" href="/fonts/acme-regular.woff2" crossorigin nonce="n0nce">'])
        ->and($html)->toContain('@font-face{font-family:"Acme Sans";font-style:normal;font-weight:400;font-display:swap;src:url("/fonts/acme-regular.woff2") format("woff2")}')
        ->and($html)->toContain('@font-face{font-family:"Acme Sans";font-style:italic;font-weight:700;font-display:swap;src:url("/fonts/acme-bold.woff2") format("woff2")}')
        ->and($html)->toContain(':root{--font-sans:"Acme Sans", sans-serif}');
});

it('sets --font-sans for a system stack and preloads nothing', function () {
    config(['lathe.theme.font' => ['family' => 'system-ui', 'fallback' => 'sans-serif']]);

    $html = fontPageSource();

    expect($html)->not->toContain('rel="preload"')
        ->and($html)->not->toContain('@font-face')
        ->and($html)->toContain(':root{--font-sans:system-ui, sans-serif}');
});

it('warns in lathe:doctor for a family with no files that is not a system stack', function () {
    config(['lathe.theme.font' => ['family' => 'Acme Sans', 'fallback' => 'sans-serif']]);

    Artisan::call('lathe:doctor');
    $lines = explode("\n", Artisan::output());

    expect($lines)->toContain('WARN config/lathe.php: theme.font.family "Acme Sans" is not a system font and has no files. Add woff2 files to theme.font.files or use a system stack such as system-ui.');
});

it('prints no font warning for a system stack', function () {
    config(['lathe.theme.font' => ['family' => 'system-ui', 'fallback' => 'sans-serif']]);

    Artisan::call('lathe:doctor');

    expect(Artisan::output())->not->toContain('theme.font');
});
