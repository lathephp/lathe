<?php

use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

require_once __DIR__.'/records.php';
require_once __DIR__.'/queues.php';

/**
 * Record each file the page asks the browser to save, as {name, href} in window.__downloads,
 * and still let the browser save it.
 */
function recordDownloads(AwaitableWebpage $page): void
{
    $page->script(<<<'JS'
        (() => {
            window.__downloads = [];
            const click = HTMLAnchorElement.prototype.click;
            HTMLAnchorElement.prototype.click = function () {
                if (this.hasAttribute('download')) {
                    window.__downloads.push({ name: this.download, href: this.href });
                }
                return click.call(this);
            };
        })();
        JS);
}

/** Open the Export modal from the Actions menu, name the file and run it as CSV. */
function runExport(AwaitableWebpage $page, string $filename): AwaitableWebpage
{
    return $page->click('button[aria-haspopup="menu"]:text-is("Actions")')
        ->click('[role="menuitem"]:has(span:text-is("Export"))')
        ->assertSeeIn('[role="dialog"] h2', 'Export')
        ->clear('#action-export-filename')
        ->type('#action-export-filename', $filename)
        ->select('#action-export-type', 'csv')
        ->click('Run Action');
}

it('default_export_downloads_in_the_browser', function () {
    $page = signedInAt('/lathe/resources/orders')->assertSee('Showing 1 to 25 of 10,000');
    recordDownloads($page);
    $numbers = $page->script("[...document.querySelectorAll('table tbody > tr input[aria-label^=\"Select \"]')].slice(0, 3).map((box) => box.getAttribute('aria-label').slice(7))");

    expect($numbers)->toHaveCount(3);

    foreach ($numbers as $number) {
        $page->click('[aria-label="Select '.$number.'"]');
    }

    runExport($page, 'orders')
        ->assertScript('window.__downloads.length', 1)
        ->assertScript('window.__downloads[0].name', 'orders.csv');

    // The test server trims a streamed body below its Content-Length, so a fetch in the page
    // fails; the file at the link the page saved is read through the app instead.
    $csv = test()->actingAs(User::where('email', 'admin@lathephp.com')->sole())
        ->get($page->script('window.__downloads[0].href'))
        ->assertOk()
        ->assertDownload('orders.csv')
        ->streamedContent();
    $lines = explode("\n", trim($csv));

    $rows = array_map(str_getcsv(...), array_slice($lines, 1));
    $orders = Order::whereIn('number', $numbers)->orderBy('id')->get();

    expect($lines[0])->toBe("\u{FEFF}Number,Customer,Status,Total,Placed")
        ->and(array_column($rows, 0))->toBe($orders->pluck('number')->all())
        ->and(array_column($rows, 3))->toBe($orders->map(fn (Order $order) => number_format($order->total_cents / 100, 2, '.', ''))->all());
    assertCleanPage($page);
})->group('regression');

it('exports every order in the queue, then offers the file in the toast and the bell', function () {
    onFileDatabase();
    $page = signedInAt('/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->keys('#app', modKey($page, 'Shift+a'))
        ->assertSee('All matching rows selected (10,000)');

    runExport($page, 'all-orders')
        ->assertSeeIn('section[aria-label="Runs"]', 'Export started.')
        ->assertNotPresent('section[aria-label="Runs"] a:text-is("Download")');

    drainQueue();

    $page->assertSeeIn('section[aria-label="Runs"]', 'Your export is ready for download.')
        ->assertPresent('section[aria-label="Runs"] a:text-is("Download")')
        ->refresh()
        ->assertPresent('[data-testid="unread-dot"]')
        ->click('[aria-label="Notifications"]')
        ->assertSeeIn('[role="dialog"][aria-label="Notifications"]', 'Your export is ready for download.')
        ->assertPresent('[role="dialog"][aria-label="Notifications"] a:has-text("Download")');
    assertCleanPage($page);
});
