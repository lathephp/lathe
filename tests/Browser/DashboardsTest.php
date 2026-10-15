<?php

use Workbench\App\Models\Customer;

require_once __DIR__.'/records.php';

// The gallery cards in page order, as the user reads their titles.
const GALLERY_CARDS = [
    'Orders', 'Orders per day', 'Paid orders', 'Top customers', 'Help', 'Clock', 'Orders by status',
    'Products by category', 'Customers by country', 'Sales by weekday', 'Order completion',
];

// Resolves with the ms since navigation once every card is past its skeleton.
const CARDS_FILLED_AT = <<<'JS'
    async () => {
        const filled = () => {
            const cards = [...document.querySelectorAll('[data-lathe-widget]')];
            return cards.length > 0 && cards.every((card) => card.querySelector('[role="status"]') === null);
        };
        while (!filled()) await new Promise((resolve) => setTimeout(resolve, 10));
        return performance.now();
    }
    JS;

// How many chart chunks the page fetched.
const CHART_CHUNKS = 'performance.getEntriesByType("resource").filter((entry) => /\/charts-[^/]+\.js$/.test(new URL(entry.name).pathname)).length';

// Each drawn part of a chart, legend and axis labels too, that sits outside its card or reaches
// up into the card title, once the chart animations have ended. A part in a scrolling list, such
// as a legend row, counts only where the list shows it; a part a hidden overflow cuts counts whole.
const CHART_OVERFLOW = <<<'JS'
    async () => {
        await new Promise((resolve) => setTimeout(resolve, 1600));
        const shown = (part, card) => {
            let { left, right, top, bottom } = part.getBoundingClientRect();
            for (let parent = part.parentElement; parent !== card; parent = parent.parentElement) {
                if (/auto|scroll/.test(getComputedStyle(parent).overflow)) {
                    const clip = parent.getBoundingClientRect();
                    left = Math.max(left, clip.left); right = Math.min(right, clip.right);
                    top = Math.max(top, clip.top); bottom = Math.min(bottom, clip.bottom);
                }
            }
            return { left, right, top, bottom };
        };
        return [...document.querySelectorAll('[data-lathe-widget]:has([role=figure])')].flatMap((card) => {
            const box = card.getBoundingClientRect();
            const title = card.querySelector('h2').getBoundingClientRect();
            return [...card.querySelectorAll('[role=figure] *')]
                .filter((part) => part.closest('.sr-only, .recharts-tooltip-wrapper') === null)
                .filter((part) => {
                    const rect = shown(part, card);
                    // An SVG cuts what it draws past its edges, such as a long axis label.
                    const edge = part.ownerSVGElement?.getBoundingClientRect() ?? box;
                    return rect.right > rect.left && rect.bottom > rect.top
                        && (rect.left < edge.left - 1 || rect.right > edge.right + 1 || rect.top < Math.max(edge.top - 1, title.bottom) || rect.bottom > edge.bottom + 1);
                })
                .map((part) => `${card.dataset.latheWidget}: <${part.tagName.toLowerCase()}> ${part.textContent.slice(0, 20)}`);
        });
    }
    JS;

it('fills every gallery card within five seconds, with named charts and their numbers as tables', function (string $project) {
    $page = signedInAt('/lathe/dashboards/widget-gallery', $project);

    // A bound for cards that never fill, not a speed budget: shared runners took 1,049 ms.
    expect($page->script(CARDS_FILLED_AT))->toBeLessThan(5000);

    $page->assertSee('Widget Gallery')
        ->assertCount('[data-lathe-widget]', 11)
        ->assertScript('[...document.querySelectorAll("[data-lathe-widget] h2")].map((title) => title.textContent)', GALLERY_CARDS)
        // Every card loads, the clock too: the workbench extension build registers its component.
        ->assertScript('[...document.querySelectorAll("[data-lathe-widget]")].filter((card) => card.textContent.includes("The widget could not be loaded.")).map((card) => card.dataset.latheWidget)', [])
        ->assertSeeIn('[data-lathe-widget="help-widget"]', 'Each card on this page is one of the 11 widget types')
        ->assertSeeIn('[data-lathe-widget="paid-orders"]', '%')
        ->assertCount('[data-lathe-widget="top-customers"] tbody tr', 5)
        ->assertCount('[data-lathe-widget="top-customers"] tbody svg', 5)
        ->assertScript('[...document.querySelectorAll("[role=figure]")].map((figure) => figure.getAttribute("aria-label"))', [
            'Orders per day', 'Orders by status', 'Products by category', 'Customers by country', 'Sales by weekday', 'Order completion',
        ])
        ->assertCount('[role=figure] .sr-only table', 6)
        ->assertScript(CHART_CHUNKS, 1);
    assertFitsViewport($page);
    assertCleanPage($page);
})->with(browserProjects());

it('shows the time on the Clock card, under its one title', function () {
    $page = signedInAt('/lathe/dashboards/widget-gallery');
    $page->script(CARDS_FILLED_AT);

    $clock = '[data-lathe-widget="custom-widget"]';
    $page->assertCount("{$clock} h2, {$clock} h3", 1)
        ->assertScript("/^\\d{1,2}:\\d{2}/.test(document.querySelector('{$clock} time').textContent)", true);
});

it('keeps every chart, its legend and its labels inside its card and below its title', function (int $width) {
    $page = signedInAt('/lathe/dashboards/widget-gallery');
    $page->resize($width, 900);
    $page->script(CARDS_FILLED_AT);

    expect($page->script(CHART_OVERFLOW))->toBe([]);
})->with(['1280 px' => 1280, '390 px' => 390]);

it('has no accessibility issue on the gallery in light and in dark', function () {
    $page = signedInAt('/lathe/dashboards/widget-gallery');
    $page->script(CARDS_FILLED_AT);

    $page->assertNoAccessibilityIssues(3);

    $page->script('document.documentElement.classList.add("dark")');
    $page->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues(3);
    assertCleanPage($page);
});

it('fetches no chart chunk on the Orders index', function () {
    $page = signedInAt('/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->assertScript(CHART_CHUNKS, 0);
});

it('changes the Orders number and its change when the range is 60 days', function (string $project) {
    $page = signedInAt('/lathe', $project);
    $page->script(CARDS_FILLED_AT);

    $orders = '[data-lathe-widget="orders"]';
    $read = fn () => $page->script("[document.querySelector('{$orders} p.text-3xl').textContent, document.querySelector('{$orders} [data-change]').textContent]");
    $page->assertSee('Sales')->assertPresent("{$orders} [data-change=\"up\"]");
    [$number, $change] = $read();

    $page->select("{$orders} select[aria-label=\"Range\"]", '60');
    $page->script(CARDS_FILLED_AT);
    // The number counts up to its new value, so the test waits for it to settle.
    $page->script('async () => { let last; while (last !== (last = document.querySelector(\'[data-lathe-widget="orders"] p.text-3xl\').textContent)) await new Promise((resolve) => setTimeout(resolve, 200)); }');

    [$sixtyNumber, $sixtyChange] = $read();
    expect($sixtyNumber)->not->toBe($number)
        ->and($sixtyChange)->not->toBe($change);
    assertCleanPage($page);
})->with(browserProjects());

it('keeps a 300 character table cell inside its third-width card at 1,280 px', function () {
    // The top customer's name fills the first row of the Top customers card.
    $top = Customer::withSum('orders', 'total_cents')->orderByDesc('orders_sum_total_cents')->orderBy('id')->firstOrFail();
    $top->forceFill(['name' => str_repeat('W', 300)])->save();

    $page = signedInAt('/lathe/dashboards/widget-gallery');
    $page->resize(1280, 800);
    $page->script(CARDS_FILLED_AT);

    $page->assertSeeIn('[data-lathe-widget="top-customers"]', str_repeat('W', 20))
        ->assertScript('document.querySelector(\'[data-lathe-widget="top-customers"]\').getBoundingClientRect().width < window.innerWidth / 2', true);
    assertFitsViewport($page);
});
