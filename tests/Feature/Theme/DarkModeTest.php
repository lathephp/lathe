<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Lathe\Lathe;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->dist = sys_get_temp_dir().'/lathe-dark-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    File::put("{$this->dist}/manifest.json", json_encode([
        'resources/js/app.tsx' => ['file' => 'assets/app-abcd1234.js', 'isEntry' => true],
        'resources/css/theme-nova.css' => ['file' => 'assets/theme-nova-0000aaaa.css', 'isEntry' => true],
    ]));
    Lathe::assets()->useDistPath($this->dist);

    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

afterEach(fn () => File::deleteDirectory($this->dist));

/** The panel page source. */
function panelSource(): string
{
    return (string) test()->actingAs(test()->admin)->get('/lathe')->getContent();
}

it('writes a nonced inline script that reads the stored choice before the entry module', function () {
    Vite::useCspNonce('abc123');

    $html = panelSource();

    preg_match('/<script nonce="abc123">(.*?)<\/script>/s', $html, $inline, PREG_OFFSET_CAPTURE);
    expect($inline)->not->toBeEmpty()
        ->and($inline[1][0])->toContain("localStorage.getItem('lathe-appearance')")
        ->and($inline[0][1])->toBeLessThan(strpos($html, '<script type="module"'));
});

it('hands the configured appearance to the script as the default for a user with no stored choice', function () {
    config(['lathe.theme.appearance' => 'dark']);

    expect(panelSource())->toContain('var d="dark"');
});

it('falls back to system when the configured appearance is not one of the three', function () {
    config(['lathe.theme.appearance' => 'sepia']);

    expect(panelSource())->toContain('var d="system"');
});
