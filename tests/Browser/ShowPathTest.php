<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/records.php';

// Resolves once every dashboard card on the page is past its skeleton.
const SHOW_PATH_CARDS_FILLED = <<<'JS'
    async () => {
        const filled = () => {
            const cards = [...document.querySelectorAll('[data-lathe-widget]')];
            return cards.length > 0 && cards.every((card) => card.querySelector('[role="status"]') === null);
        };
        while (!filled()) await new Promise((resolve) => setTimeout(resolve, 10));
        return true;
    }
    JS;

// The colours the gallery charts paint: the Orders per day area and the Order completion track.
const SHOW_PATH_AREA_FILL = 'getComputedStyle(document.querySelector(\'[data-lathe-widget="orders-per-day"] path.recharts-area-area\')).fill';
const SHOW_PATH_TRACK_FILL = 'getComputedStyle(document.querySelector(\'[data-lathe-widget="order-completion"] path.recharts-radial-bar-background-sector\')).fill';

/**
 * Run lathe:preset against a copy of the package config, the shadcn API answering with the
 * lyra fixture, and apply the theme it wrote to the running panel.
 */
function applyLyraPreset(): void
{
    $configDir = sys_get_temp_dir().'/lathe-show-path-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($configDir);
    File::copy(dirname(__DIR__, 2).'/config/lathe.php', "{$configDir}/lathe.php");
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($configDir));

    $configPath = app()->configPath();
    app()->useConfigPath($configDir);
    Http::fake(['ui.shadcn.com/init*' => Http::response(json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/shadcn/preset-lyra.json'), true))]);

    // aurjagS decodes to the lyra style, the zinc base, the blue theme and the medium radius.
    test()->artisan('lathe:preset', ['preset' => 'aurjagS'])->assertExitCode(0);

    $theme = (require "{$configDir}/lathe.php")['theme'];
    app()->useConfigPath($configPath);

    expect($theme['style'])->toBe('lyra');
    config(['lathe.theme' => $theme]);
}

it('walks the Sales dashboard, the Widget Gallery, a theme preset and dark mode', function () {
    $page = signedInAt('/lathe');

    $page->assertSee('Sales')
        ->assertScript(SHOW_PATH_CARDS_FILLED, true);
    assertCleanPage($page);

    $page->navigate('/lathe/dashboards/widget-gallery')
        ->assertScript(SHOW_PATH_CARDS_FILLED, true)
        ->assertCount('[data-lathe-widget]', 11);
    assertCleanPage($page);

    applyLyraPreset();

    // The lyra fixture's light chart-1, light muted, primary and dark muted colours.
    $page->refresh()
        ->assertScript(SHOW_PATH_CARDS_FILLED, true)
        ->assertScript('document.body.className', 'style-lyra')
        ->assertScript(roundedCss(SHOW_PATH_AREA_FILL), 'oklch(0.809 0.105 251.813)')
        ->assertScript(roundedCss(SHOW_PATH_TRACK_FILL), 'oklch(0.967 0.001 286.375)');
    assertCleanPage($page);

    // The gallery has no primary button, so the Mark Shipped modal on the Orders index shows one.
    $page->navigate('/lathe/resources/orders')
        ->click('tbody tr:first-child input[type="checkbox"]')
        ->click('button[aria-haspopup="menu"]:text-is("Actions")')
        ->click('[role="menuitem"]:has-text("Mark Shipped")')
        ->assertScript(roundedCss('getComputedStyle(document.querySelector(\'[role="dialog"] button[type="submit"]\')).backgroundColor'), 'oklch(0.488 0.243 264.376)');
    assertCleanPage($page);

    $page->navigate('/lathe/dashboards/widget-gallery')
        ->assertScript(SHOW_PATH_CARDS_FILLED, true)
        ->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->click('[role="menuitemradio"]:has-text("Dark")')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript(roundedCss(SHOW_PATH_TRACK_FILL), 'oklch(0.274 0.006 286.033)');
    assertCleanPage($page);
});
