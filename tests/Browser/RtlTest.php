<?php

use Lathe\Lathe;

require_once __DIR__.'/records.php';

const SIDEBAR_RECT = 'JSON.stringify(document.getElementById("lathe-sidebar").getBoundingClientRect())';

it('lays the panel out right to left', function () {
    Lathe::enableRTL();

    $page = signedInAt('/lathe/resources/users');
    $viewport = (int) $page->script('window.innerWidth');
    $sidebar = json_decode((string) $page->script(SIDEBAR_RECT), true);

    // Sidebar on the inline end: its right edge meets the viewport's right edge.
    expect($page->script('document.documentElement.dir'))->toBe('rtl')
        ->and((int) round($sidebar['right']))->toBe($viewport)
        ->and($sidebar['left'])->toBeGreaterThan(0);

    // The sidebar toggle icon is mirrored with a 180 degree turn.
    $transform = $page->script('getComputedStyle(document.querySelector("[aria-label=\"Toggle sidebar\"] svg")).rotate');
    expect($transform)->toBe('180deg');

    // An email keeps its own direction inside a right-to-left row.
    $page->assertScript('document.querySelector("table tbody td [dir=ltr]").textContent.includes("@")', true);
});

it('keeps the sidebar on the left when the panel is left to right', function () {
    $page = signedInAt('/lathe/resources/users');
    $sidebar = json_decode((string) $page->script(SIDEBAR_RECT), true);

    expect($page->script('document.documentElement.dir'))->toBe('ltr')
        ->and((int) round($sidebar['left']))->toBe(0)
        ->and($page->script('getComputedStyle(document.querySelector("[aria-label=\"Toggle sidebar\"] svg")).rotate'))->toBe('none');
});
