<?php

use Illuminate\Support\Facades\File;
use Lathe\Lathe;
use Workbench\App\Models\User;

// The 22 @lathephp/ui entries a plugin loads only when it imports them.
const IMPORT_MAP_UI_SUBPATHS = [
    'breadcrumb', 'button-group', 'calendar', 'chart', 'code-editor', 'collapsible', 'combobox', 'command',
    'direction', 'empty', 'field', 'hover-card', 'input-group', 'item', 'otp-field', 'pagination',
    'scroll-area', 'shimmer-bar', 'sidebar', 'sonner', 'toggle', 'toggle-group',
];

beforeEach(function () {
    $this->dist = sys_get_temp_dir().'/lathe-import-map-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->dist}/assets");
    $specifiers = ['react', 'react/jsx-runtime', 'react/compiler-runtime', 'react-dom', 'react-dom/client', '@lathephp/core', '@lathephp/ui'];

    foreach (IMPORT_MAP_UI_SUBPATHS as $name) {
        $specifiers[] = "@lathephp/ui/{$name}";
    }

    $manifest = ['resources/js/app.tsx' => ['file' => 'assets/app-abcd1234.js', 'isEntry' => true]];

    foreach ($specifiers as $specifier) {
        $manifest["lathe-vendor:{$specifier}"] = ['file' => 'assets/'.str_replace(['@', '/'], ['', '-'], $specifier).'-12345678.js', 'isEntry' => true];
    }

    File::put("{$this->dist}/manifest.json", json_encode($manifest));
    Lathe::assets()->useDistPath($this->dist);

    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

afterEach(fn () => File::deleteDirectory($this->dist));

it('maps each @lathephp/ui subpath to its own file under the asset hash', function () {
    $html = $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();
    preg_match('/<script type="importmap"[^>]*>(.*?)<\/script>/s', $html, $match);
    $imports = json_decode($match[1], true)['imports'];

    foreach (IMPORT_MAP_UI_SUBPATHS as $name) {
        expect($imports)->toHaveKey("@lathephp/ui/{$name}")
            ->and($imports["@lathephp/ui/{$name}"])->toBe("/lathe/assets/abcd1234/lathephp-ui-{$name}-12345678.js");
    }

    // The plan counts 28 entries; react/compiler-runtime, which the React Compiler output imports, makes 29.
    expect($imports)->toHaveCount(29);
});
