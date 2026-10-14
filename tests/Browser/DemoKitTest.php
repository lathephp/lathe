<?php

use Workbench\App\Models\Product;

require_once __DIR__.'/records.php';

// The 27 modules of the @lathephp/ui entry, then its 22 subpaths.
const UI_COMPONENTS = [
    'accordion', 'alert', 'alert-dialog', 'avatar', 'badge', 'button', 'card', 'checkbox', 'dialog', 'dropdown-menu',
    'input', 'kbd', 'label', 'popover', 'progress', 'radio-group', 'select', 'separator', 'sheet', 'skeleton',
    'slider', 'spinner', 'switch', 'table', 'tabs', 'textarea', 'tooltip',
    'breadcrumb', 'button-group', 'calendar', 'chart', 'code-editor', 'collapsible', 'combobox', 'command', 'direction',
    'empty', 'field', 'hover-card', 'input-group', 'item', 'otp-field', 'pagination', 'scroll-area', 'shimmer-bar',
    'sidebar', 'sonner', 'toggle', 'toggle-group',
];

it('renders every @lathephp/ui component on the UI Gallery and toasts on g then d', function () {
    $page = signedInAt('/lathe/pages/ui-gallery');

    // The subpath demos load through the import map; the direction one reads its provider.
    $page->assertSeeIn('[data-component="toggle-group"]', 'Left')
        ->assertSeeIn('[data-component="direction"]', 'rtl')
        ->assertSeeIn('[data-component="code-editor"]', '"status"')
        ->assertMissing('[data-demo-loading]');

    expect($page->script("[...document.querySelectorAll('[data-component]')].map((node) => node.dataset.component)"))->toBe(UI_COMPONENTS);

    $page->press('Emit event')
        ->assertSee('Events received: 1')
        ->keys('#app', 'g')
        ->keys('#app', 'd')
        ->assertSee('You pressed g then d.')
        ->assertNoJavaScriptErrors();

    // CodeMirror, Sonner and the Base UI scroll area inject <style> tags without the CSP nonce,
    // so the strict policy blocks four inline styles; those are the kit's to fix.
    expect($page->script('window.__lathe.csp'))->toBe(array_fill(0, 4, 'style-src-elem blocked inline'))
        ->and($page->script('window.__lathe.errors'))->toBe([]);
});

/**
 * How many demo badges each render hook position shows on the page.
 *
 * @return array<string, int>
 */
function hookBadges($page): array
{
    $page->assertPresent('[data-demo-hook="head"]');

    return $page->script(<<<'JS'
        Object.fromEntries(['head', 'header:end', 'sidebar:before', 'sidebar:after', 'page:before', 'page:after', 'footer:before',
            'index:header-actions', 'detail:header-actions', 'auth:after-form']
            .map((position) => [position, document.querySelectorAll(`[data-lathe-hook="${position}"] [data-demo-hook="${position}"]`).length]))
        JS);
}

it('shows a badge at each of the 10 render hook positions on Users', function () {
    forgetServerSession();
    $login = latheVisit('/lathe/login', 'desktop-chrome');

    expect(hookBadges($login)['auth:after-form'])->toBe(1);

    signInThroughForm($login, 'admin@lathephp.com');
    $page = $login->navigate('/lathe/resources/users');
    $index = hookBadges($page->assertPresent('[data-demo-hook="index:header-actions"]'));
    $detail = hookBadges($page->navigate('/lathe/resources/users/1')->assertPresent('[data-demo-hook="detail:header-actions"]'));

    expect($index)->toBe([
        'head' => 1, 'header:end' => 1, 'sidebar:before' => 1, 'sidebar:after' => 1, 'page:before' => 1, 'page:after' => 1,
        'footer:before' => 1, 'index:header-actions' => 1, 'detail:header-actions' => 0, 'auth:after-form' => 0,
    ])->and($detail['detail:header-actions'])->toBe(1);
    assertCleanPage($page);
});

it('leaves out the two Users-only badges on Orders', function () {
    $index = hookBadges(signedInAt('/lathe/resources/orders')->assertPresent('table tbody > tr'));
    $page = signedInAt('/lathe/resources/orders/1')->assertPresent('[data-demo-hook="page:after"]');

    expect($index)->toBe([
        'head' => 1, 'header:end' => 1, 'sidebar:before' => 1, 'sidebar:after' => 1, 'page:before' => 1, 'page:after' => 1,
        'footer:before' => 1, 'index:header-actions' => 0, 'detail:header-actions' => 0, 'auth:after-form' => 0,
    ])->and(hookBadges($page)['detail:header-actions'])->toBe(0);
    assertCleanPage($page);
});

it('opens System Status from the Demo Kit section of the sidebar', function () {
    $page = signedInAt('/lathe');

    $page->click('#lathe-sidebar div:has(> h2:text-is("Demo Kit")) a[href$="/pages/system-status"]')
        ->assertPathIs('/lathe/pages/system-status')
        ->assertSee('Page key')
        ->assertSeeIn('main section > h1', 'System Status');

    expect($page->script("Object.fromEntries([...document.querySelectorAll('main dl > div')].map((row) => [row.querySelector('dt').textContent, row.querySelector('dd').textContent]))"))
        ->toBe(['Page key' => 'system-status', 'Base path' => '/lathe/pages/system-status', 'Sub-path' => '—']);
    assertCleanPage($page);
});

it('saves 4 from the StarRating field and shows 4 stars on the index and the detail', function () {
    $product = Product::query()->latest('id')->firstOrFail();
    $page = signedInAt("/lathe/resources/products/{$product->id}/edit");
    $filled = '[data-star-rating] [data-filled]';

    $page->click('[data-star-rating] button[aria-label="4 stars"]')
        ->assertAttribute('[data-star-rating] button[aria-label="4 stars"]', 'aria-pressed', 'true')
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/products/{$product->id}")
        ->assertSee('4 / 5')
        ->assertCount("main {$filled}", 4);

    expect($product->fresh()?->getAttribute('rating'))->toBe(4);

    // text-amber-400 is in the workbench's own stylesheet only, so the color shows that stylesheet is linked.
    $page->navigate('/lathe/resources/products')
        ->assertCount("table tbody tr:first-child {$filled}", 4)
        ->assertScript("getComputedStyle(document.querySelector('table tbody tr:first-child [data-filled]')).color", 'lab(80.1641 16.6016 99.2089)');
    assertCleanPage($page);
});

it('narrows Products to the Premium tier of the PriceTier filter', function () {
    $premium = number_format(Product::query()->where('price_cents', '>=', 20000)->count());
    $page = signedInAt('/lathe/resources/products');

    $page->assertSee('Showing 1 to 25 of 500')
        ->click('Filters')
        ->click('[data-price-tier] button:text-is("Premium")')
        ->click('Apply')
        ->assertSee("Showing 1 to 25 of {$premium}")
        ->assertScript('decodeURIComponent(location.search)', '?filter[price-tier]=premium');
    assertCleanPage($page);
});
