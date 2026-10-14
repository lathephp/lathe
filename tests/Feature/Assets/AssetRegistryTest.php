<?php

use Illuminate\Support\Facades\File;
use Lathe\Assets\AssetRegistry;
use Lathe\Exceptions\LatheException;
use Lathe\Lathe;
use Lathe\Plugins\Plugin;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lathe-registry-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->dir);
    File::put("{$this->dir}/theme.css", 'body{color:red}');
    File::put("{$this->dir}/tool.js", 'export const beat = 1;');
});

afterEach(fn () => File::deleteDirectory($this->dir));

function cspDirective(string $header, string $directive): string
{
    preg_match("/{$directive} ([^;]*)/", $header, $match);

    return $match[1] ?? '';
}

it('loads a style from Lathe::style() on every panel page', function () {
    Lathe::style('theme', "{$this->dir}/theme.css");

    $this->get('/lathe/login')
        ->assertOk()
        ->assertSee('<link rel="stylesheet" href="/lathe/styles/theme-15c42ab7.css"', false);
});

it('lists a script from Lathe::script() as a plugin module', function () {
    Lathe::script('tool', "{$this->dir}/tool.js");

    expect(Lathe::plugins()->moduleUrls())->toContain('/lathe/modules/tool-15dcd957.js');
});

it('registers a batch of scripts and styles from one array', function () {
    app(AssetRegistry::class)->register([
        'scripts' => ['tool' => "{$this->dir}/tool.js"],
        'styles' => ['theme' => "{$this->dir}/theme.css"],
    ]);

    expect(Lathe::plugins()->moduleUrls())->toContain('/lathe/modules/tool-15dcd957.js');
    $this->get('/lathe/login')->assertSee('/lathe/styles/theme-15c42ab7.css', false);
});

it('lists a remote script and loads a remote style as given', function () {
    app(AssetRegistry::class)->remoteScript('cdn', 'https://cdn.example.test/a.js');
    app(AssetRegistry::class)->remoteStyle('cdn', 'https://cdn.example.test/a.css');

    expect(Lathe::plugins()->moduleUrls())->toContain('https://cdn.example.test/a.js');
    $this->get('/lathe/login')->assertSee('<link rel="stylesheet" href="https://cdn.example.test/a.css"', false);
});

it('sends a remote https URL given to Lathe::script() the remote way', function () {
    Lathe::script('cdn', 'https://cdn.example.test/a.js');

    expect(Lathe::plugins()->moduleUrls())->toContain('https://cdn.example.test/a.js');
});

it('refuses a remote asset that is not https', function (string $method, string $url) {
    expect(fn () => app(AssetRegistry::class)->{$method}('cdn', $url))->toThrow(LatheException::class);
    expect(fn () => Lathe::script('cdn', $url))->toThrow(LatheException::class);
})->with([
    ['remoteScript', 'http://x.test/a.js'],
    ['remoteStyle', 'http://x.test/a.css'],
    ['remoteScript', '//x.test/a.js'],
    ['remoteScript', 'javascript:alert(1)'],
]);

it('adds a remote origin to the CSP of the response that registered it, and no other', function () {
    config(['workbench.strict_csp' => true]);
    Lathe::registerPlugins(new class extends Plugin
    {
        public function serving($request): void
        {
            Lathe::script('cdn', 'https://cdn.example.test/a.js');
            Lathe::style('cdn', 'https://styles.example.test/a.css');
        }
    });
    $this->artisan('migrate')->assertSuccessful();
    $admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);

    $served = $this->actingAs($admin)->get('/lathe')->assertOk()->headers->get('Content-Security-Policy');

    expect(cspDirective($served, 'script-src'))->toContain('https://cdn.example.test')
        ->and(cspDirective($served, 'script-src'))->not->toContain('styles.example.test')
        ->and(cspDirective($served, 'style-src'))->toContain('https://styles.example.test')
        ->and(cspDirective($served, 'style-src'))->not->toContain('cdn.example.test');

    // The same user, a later response: the module route runs no plugin serving().
    $later = $this->get('/lathe/modules/none-00000000.js')->headers->get('Content-Security-Policy');

    expect((string) $later)->not->toContain('example.test')
        ->and(Lathe::plugins()->moduleUrls())->not->toContain('https://cdn.example.test/a.js');
});

it('adds the origin of a remote asset registered at boot to every panel response', function () {
    config(['workbench.strict_csp' => true]);
    Lathe::script('cdn', 'https://cdn.example.test/a.js');

    $header = $this->get('/lathe/login')->headers->get('Content-Security-Policy');

    expect(cspDirective((string) $header, 'script-src'))->toContain('https://cdn.example.test');
});
