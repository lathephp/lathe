<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->dist = sys_get_temp_dir().'/lathe-theme-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    $manifest = ['resources/js/app.tsx' => ['file' => 'assets/app-abcd1234.js', 'isEntry' => true, 'css' => ['assets/app-ffff0000.css']]];
    foreach (['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera'] as $style) {
        $manifest["resources/css/theme-{$style}.css"] = ['file' => "assets/theme-{$style}-0000aaaa.css", 'isEntry' => true];
    }
    File::put("{$this->dist}/manifest.json", json_encode($manifest));
    Lathe::assets()->useDistPath($this->dist);

    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

afterEach(fn () => File::deleteDirectory($this->dist));

it('links the nova style by default and puts its class on the body', function () {
    $html = (string) $this->actingAs($this->admin)->get('/lathe')->getContent();

    // The panel's own built stylesheets; the workbench links its extension stylesheet beside them.
    preg_match_all('#<link rel="stylesheet" href="(/lathe/assets/[^"]+)"#', $html, $links);
    expect($links[1])->toBe(['/lathe/assets/abcd1234/app-ffff0000.css', '/lathe/assets/abcd1234/theme-nova-0000aaaa.css'])
        ->and($html)->toContain('<body class="style-nova">');
});

it('links only the configured style', function () {
    config(['lathe.theme.style' => 'lyra']);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->getContent();

    preg_match_all('/theme-[a-z]+-0000aaaa\.css/', $html, $sheets);
    expect($sheets[0])->toBe(['theme-lyra-0000aaaa.css'])
        ->and($html)->toContain('<body class="style-lyra">');
});

it('lists all 8 styles in the published config comment and defaults to nova', function () {
    $path = dirname(__DIR__, 3).'/config/lathe.php';

    expect((string) file_get_contents($path))->toContain("| null keeps the style's own value. The style is one of nova (the default),\n    | vega, lyra, maia, mira, luma, rhea or sera.")
        ->and((require $path)['theme']['style'])->toBe('nova');
});

it('draws every variant of the kit Button and Badge in each style', function (string $style) {
    $root = dirname(__DIR__, 3);
    $css = (string) file_get_contents("{$root}/resources/css/styles/style-{$style}.css");

    foreach (['button' => 'packages/ui/src/button.tsx', 'badge' => 'packages/ui/src/badge.tsx'] as $component => $source) {
        preg_match_all("/'(cn-{$component}-variant-[a-z-]+)'/", (string) file_get_contents("{$root}/{$source}"), $variants);

        expect($variants[1])->not->toBeEmpty();
        foreach ($variants[1] as $variant) {
            expect($css)->toContain(".{$variant} {");
        }
    }
})->with(['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera']);
