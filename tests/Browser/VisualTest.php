<?php

/*
 * Screenshot baselines: login, dashboard, index, detail, form and modal, light and dark, in the
 * browser --browser names, plus the Orders index and form right to left in Chrome. A CI run of the
 * three desktop browsers makes 38 images, named "<browser> <screen> <theme>", which Pest stores
 * in tests/.pest/snapshots/Browser/VisualTest.
 *
 * Baselines are made and compared only on CI (the CI variable is set), because fonts differ
 * between machines and the baselines must come from the CI image. Outside CI each test opens its
 * screen and then skips, so it writes no image and none can reach git. When the folder is not
 * committed yet, the CI browser job runs this file with --update-snapshots and uploads the folder
 * as the screenshot-baselines artifact. The phone projects skip the file.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lathe\Lathe;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Playwright;
use Workbench\Database\Seeders\DemoSeeder;

require_once __DIR__.'/records.php';

dataset('visual screens', function () {
    $browser = Playwright::defaultBrowserType()->value;

    foreach (['login', 'dashboard', 'index', 'detail', 'form', 'modal'] as $screen) {
        foreach (['light', 'dark'] as $theme) {
            yield "{$browser} {$screen} {$theme}" => [$screen, $theme];
        }
    }

    if ($browser === 'chrome') {
        yield 'chrome index rtl' => ['index', 'rtl'];
        yield 'chrome form rtl' => ['form', 'rtl'];
    }
});

/** Open one screen with the seeded orders and wait for its lazy content. */
function visualScreen(string $screen): AwaitableWebpage
{
    if ($screen === 'login') {
        forgetServerSession();

        return latheVisit('/lathe/login', 'desktop-chrome')->assertSee('Sign in');
    }

    $page = signedInAt('/lathe');

    // A value widget counts up to its number for 600 ms unless the browser asks for reduced motion.
    $playwright = (fn () => $this->page)->call($page);
    (fn () => $this->processVoidResponse(Client::instance()->execute($this->guid, 'emulateMedia', ['reducedMotion' => 'reduce'])))->call($playwright);

    $page = $page->navigate(match ($screen) {
        'dashboard' => '/lathe',
        'index' => '/lathe/resources/orders',
        'detail', 'modal' => '/lathe/resources/orders/12',
        'form' => '/lathe/resources/orders/12/edit',
    });

    // The render hooks, the sidebar's icon set and the widgets load after first paint.
    $page->assertSee('page:after')
        ->assertScript(<<<'JS'
            (() => {
                const links = [...document.querySelectorAll('#lathe-sidebar nav a')];
                return links.length > 0
                    && links.every((link) => link.querySelector('svg'))
                    && document.querySelector('main [role="status"][aria-label="Loading"]') === null;
            })()
            JS, true);

    return match ($screen) {
        'dashboard', 'form' => $page,
        'index' => $page->assertSee('Showing 1 to 25 of 10,000')->assertVisible('main button:has-text("Actions")'),
        'detail' => $page->assertVisible('main header button:has-text("Actions")')->assertSee('Action Events'),
        'modal' => $page->assertVisible('main header button:has-text("Actions")')
            ->click('main header button.text-destructive')
            ->assertVisible('[role="dialog"]'),
    };
}

beforeEach(function () {
    if (str_starts_with((string) getenv('LATHE_BROWSER_PROJECT'), 'phone-')) {
        $this->markTestSkipped('The desktop browsers cover the screenshots.');
    }

    // assertScreenshotMatches adds an inline style tag, which the strict policy blocks.
    config(['workbench.strict_csp' => false]);

    // The seeder counts its dates back from midnight today, so it runs again on a fixed day.
    $this->travelTo('2026-06-15 09:00:00');
    Schema::withoutForeignKeyConstraints(function () {
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }
    });
    $this->seed(DemoSeeder::class);
});

it('matches the screenshot baseline', function (string $screen, string $theme) {
    if ($theme === 'rtl') {
        Lathe::enableRTL();
    }

    config(['lathe.theme.appearance' => $theme === 'dark' ? 'dark' : 'light']);

    $page = visualScreen($screen);

    if (getenv('CI') === false) {
        $this->markTestSkipped('Screenshot baselines are made and compared on CI only.');
    }

    // The modal shows over a dimmed page, so the viewport holds all of it.
    $page->assertScreenshotMatches(fullPage: $screen !== 'modal');
})->with('visual screens');
