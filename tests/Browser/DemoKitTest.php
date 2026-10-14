<?php

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
