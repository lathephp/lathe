<?php

use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Post;

require_once __DIR__.'/records.php';

const THEME_STYLES = ['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera'];

// The style names in the theme stylesheets this page fetched.
const FETCHED_STYLES = 'performance.getEntriesByType("resource").map((entry) => new URL(entry.name).pathname.match(/\/theme-([a-z]+)-[0-9a-z]+\.css$/)).filter(Boolean).map((match) => match[1])';

/** Open the Mark Shipped action on the first order of the index. */
function openShipAction(AwaitableWebpage $page): AwaitableWebpage
{
    return $page->click('tbody tr:first-child input[type="checkbox"]')
        ->click('button[aria-haspopup="menu"]:text-is("Actions")')
        ->click('[role="menuitem"]:has-text("Mark Shipped")')
        ->assertSeeIn('[role="dialog"] h2', 'Mark Shipped');
}

it('fetches only the stylesheet of the configured style', function (string $style) {
    config(['lathe.theme.style' => $style]);

    $page = signedInAt('/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25')
        ->assertScript('document.body.className', "style-{$style}")
        ->assertScript(FETCHED_STYLES, [$style]);
    assertCleanPage($page);
})->with(THEME_STYLES);

it('writes the default radius to --radius', function () {
    $page = signedInAt('/lathe/resources/orders');

    $page->assertScript('getComputedStyle(document.body).getPropertyValue("--radius").trim()', '0.625rem');
});

it('keeps square corners under lyra whatever the radius', function () {
    config(['lathe.theme.style' => 'lyra', 'lathe.theme.radius' => '1rem']);

    $page = signedInAt('/lathe/resources/orders');

    $page->assertScript('getComputedStyle(document.body).getPropertyValue("--radius").trim()', '0');
});

it('paints a primary button with the configured primary colour', function () {
    config(['lathe.theme.colors.primary' => 'oklch(0.62 0.19 259)']);

    $page = openShipAction(signedInAt('/lathe/resources/orders'));

    $page->assertScript('getComputedStyle(document.querySelector(\'[role="dialog"] button[type="submit"]\')).backgroundColor', 'oklch(0.62 0.19 259)');
    assertCleanPage($page);
});

it('marks the structural elements with data-lathe hooks for custom CSS', function () {
    $page = signedInAt('/lathe/resources/orders');

    foreach (['shell', 'sidebar', 'header', 'content', 'footer', 'toolbar', 'table', 'card'] as $hook) {
        $page->assertPresent("[data-lathe-{$hook}]");
    }

    openShipAction($page)->assertPresent('[role="dialog"][data-lathe-modal]');

    // The Orders create page answers 403 for everyone, so the Orders edit form stands in.
    $page->navigate('/lathe/resources/orders/12/edit')->assertPresent('form[data-lathe-form]');

    $post = Post::onlyTrashed()->firstOrFail();
    $page->navigate("/lathe/resources/posts/{$post->id}")
        ->assertSeeIn('[data-lathe-banner]', 'Soft Deleted');
    assertCleanPage($page);
});
