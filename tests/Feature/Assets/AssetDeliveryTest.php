<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Workbench\App\Models\User;

const SPECIFIERS = ['react', 'react/jsx-runtime', 'react/compiler-runtime', 'react-dom', 'react-dom/client', '@lathephp/core', '@lathephp/ui'];

beforeEach(function () {
    $this->dist = sys_get_temp_dir().'/lathe-dist-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    $manifest = [
        'resources/js/app.tsx' => ['file' => 'assets/app-abcd1234.js', 'isEntry' => true, 'css' => ['assets/app-ffff0000.css']],
        'resources/js/pages/Welcome.tsx' => ['file' => 'assets/Welcome-00aa11bb.js'],
    ];
    foreach (SPECIFIERS as $specifier) {
        $file = 'assets/'.str_replace(['@', '/'], ['', '-'], $specifier).'-12345678.js';
        $manifest["lathe-vendor:{$specifier}"] = ['file' => $file, 'isEntry' => true];
    }
    foreach ($manifest as $entry) {
        File::put("{$this->dist}/{$entry['file']}", 'export default 1;');
        foreach ($entry['css'] ?? [] as $css) {
            File::put("{$this->dist}/{$css}", 'body{}');
        }
    }
    File::put("{$this->dist}/manifest.json", json_encode($manifest));
    File::put("{$this->dist}/secret.txt", 'not listed');
    Lathe::assets()->useDistPath($this->dist);

    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
    $this->editor = User::forceCreate(['name' => 'Editor User', 'email' => 'editor@lathephp.com', 'password' => bcrypt('password')]);
});

afterEach(fn () => File::deleteDirectory($this->dist));

it('takes the asset hash from the entry file name', function () {
    expect(Lathe::assets()->hash())->toBe('abcd1234');
});

it('serves a manifest file immutably, with an ETag and no cookie', function () {
    $response = $this->get('/lathe/assets/abcd1234/app-abcd1234.js')
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('X-Lathe-Assets', 'abcd1234');

    expect($response->headers->get('ETag'))->not->toBeEmpty()
        ->and($response->headers->getCookies())->toBe([]);

    $this->get('/lathe/assets/abcd1234/app-abcd1234.js', ['If-None-Match' => $response->headers->get('ETag')])
        ->assertStatus(304);
    $this->get('/lathe/assets/abcd1234/app-ffff0000.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
});

it('answers 404 for an unknown hash, an unlisted file or a path that climbs', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/lathe/assets/00000000/app-abcd1234.js',
    '/lathe/assets/abcd1234/secret.txt',
    '/lathe/assets/abcd1234/..%2Fmanifest.json',
    '/lathe/assets/abcd1234/../manifest.json',
]);

it('renders the root view with the import map before the first module script', function () {
    Lathe::registerPlugins(Beacon::make());
    $html = $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    expect($html)->toContain('<html lang="en" dir="ltr">')
        ->toContain('<meta name="csrf-token"')
        ->toContain('<noscript>Lathe needs JavaScript.</noscript>')
        ->and(strpos($html, '<script type="importmap"'))->toBeLessThan(strpos($html, '<script type="module"'));

    preg_match('/<script type="importmap"[^>]*>(.*?)<\/script>/s', $html, $match);
    $imports = json_decode($match[1], true)['imports'];
    expect(array_keys($imports))->toEqualCanonicalizing(SPECIFIERS);
    foreach ($imports as $url) {
        expect($url)->toStartWith('/lathe/assets/abcd1234/');
    }

    $hash = substr((string) hash_file('sha256', dirname(__DIR__, 2).'/Fixtures/assets/beacon.js'), 0, 8);
    expect($html)->toContain("<link rel=\"modulepreload\" href=\"/lathe/modules/beacon-{$hash}.js\"")
        ->toContain('<script type="module" src="/lathe/assets/abcd1234/app-abcd1234.js"')
        ->toContain('<link rel="stylesheet" href="/lathe/assets/abcd1234/app-ffff0000.css"');
});

it('puts the CSP nonce on every script, style and import map tag', function () {
    Vite::useCspNonce('n0nce');

    $html = $this->actingAs($this->admin)->get('/lathe')->getContent();

    preg_match_all('/<(script|style|link)\b[^>]*>/', $html, $tags);
    $scripted = array_filter($tags[0], fn ($tag) => ! str_contains($tag, 'csrf') && (str_starts_with($tag, '<script') || str_starts_with($tag, '<style') || str_contains($tag, 'stylesheet') || str_contains($tag, 'modulepreload')));
    expect($scripted)->not->toBeEmpty();
    foreach ($scripted as $tag) {
        expect($tag)->toContain('nonce="n0nce"');
    }
});

it('shares the version and asset hash on every page', function () {
    $this->actingAs($this->admin)->get('/lathe')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('latheVersion', Lathe::version())
            ->where('assetHash', 'abcd1234')
            ->where('appName', config('lathe.name'))
            ->where('basePath', '/lathe')
            ->where('auth.user.email', 'admin@lathephp.com')
            ->has('flash'));
});

it('leaves once-per-session props out of a visit that has them', function () {
    $page = $this->actingAs($this->admin)->get('/lathe', [
        'X-Inertia' => 'true',
        'X-Inertia-Except-Once-Props' => 'appName,basePath',
    ])->json('props');

    expect($page)->not->toHaveKey('appName')->not->toHaveKey('basePath')
        ->toHaveKey('auth')->toHaveKey('flash');
});

it('shows the user who is signed in now, not an earlier one', function () {
    $this->actingAs($this->admin)->get('/lathe');
    $this->post('/lathe/logout');
    $this->actingAs($this->editor)->get('/lathe')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.email', 'editor@lathephp.com'));
});
