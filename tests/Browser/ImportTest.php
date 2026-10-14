<?php

use Illuminate\Support\Facades\Artisan;
use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Product;

require_once __DIR__.'/records.php';

/**
 * Run the queued jobs in this process. The browser app runs in the test process on a database
 * the test's transaction wraps, so a second worker process would not see the import rows.
 */
function runQueue(): void
{
    Artisan::call('queue:work', ['--stop-when-empty' => true]);
}

/** Open the import modal on the products list and choose a fixture file. */
function chooseImportFile(string $file): AwaitableWebpage
{
    config(['queue.default' => 'database']);
    $page = signedInAt('/lathe/resources/products');

    $page->click('button[aria-haspopup="menu"]:has-text("Actions")')
        ->click('[role="menu"] button:has-text("Import")')
        ->assertSeeIn('[role="dialog"]', 'Choose a CSV or XLSX file.')
        ->attach('[role="dialog"] input[type="file"]', dirname(__DIR__, 2).'/workbench/fixtures/'.$file)
        ->assertSeeIn('[role="dialog"]', 'Column in file');

    return $page;
}

it('imports products.csv: maps, validates, imports and reports through the toast and the bell', function () {
    $before = Product::count();
    $page = chooseImportFile('products.csv');

    $page->assertScript('[...document.querySelectorAll("[role=dialog] select")].map((s) => s.selectedOptions[0].textContent).join(",")', 'Name,Sku,Price')
        ->assertScript('[...document.querySelectorAll("[role=dialog] select")][0].options[0].textContent', 'Skip')
        ->click('[role="dialog"] button:has-text("Continue")')
        ->assertSeeIn('[role="dialog"]', 'Validate the file before importing. Nothing is saved.')
        ->click('[role="dialog"] button:has-text("Validate")')
        ->assertSeeIn('[role="dialog"]', 'Validating the file…');

    runQueue();

    $page->assertSeeIn('[role="dialog"]', '2 will be created, 1 will be updated, 0 failed');
    expect(Product::count())->toBe($before);

    $page->click('[role="dialog"] button:has-text("Import")')
        ->assertSee('Import started.')
        ->assertNotPresent('[role="dialog"]');

    runQueue();

    $page->assertSeeIn('section[aria-label="Runs"]', 'Your import has completed.')
        ->assertSeeIn('section[aria-label="Runs"]', '2 created, 1 updated, 0 skipped, 0 failed.')
        ->assertPresent('[data-testid="unread-dot"]');

    expect(Product::count())->toBe(502)
        ->and(Product::where('sku', 'SKU-90001')->value('name'))->toBe('Trail Mug');
    assertCleanPage($page);
});

it('reports the failed row of products-bad.csv in the dry run with a failed rows link', function () {
    $page = chooseImportFile('products-bad.csv');

    $page->click('[role="dialog"] button:has-text("Continue")')
        ->click('[role="dialog"] button:has-text("Validate")');

    runQueue();

    $page->assertSeeIn('[role="dialog"]', '2 will be created, 0 will be updated, 1 failed')
        ->assertSeeIn('[role="dialog"]', 'Row 4: price: The price field must be a number.')
        ->assertSeeIn('[role="dialog"] a[target="_blank"]', 'Download Failed Rows');
    assertCleanPage($page);
});
