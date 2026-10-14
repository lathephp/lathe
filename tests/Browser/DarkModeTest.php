<?php

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Client;

require_once __DIR__.'/records.php';

const HTML_IS_DARK = 'document.documentElement.classList.contains("dark")';
const CHART_ONE = 'getComputedStyle(document.documentElement).getPropertyValue("--chart-1").trim()';

/** Pick Light, Dark or System in the user menu. */
function chooseAppearance(AwaitableWebpage $page, string $label): void
{
    $page->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->click("[role=\"menuitemradio\"]:has-text(\"{$label}\")");
}

/** Run a script in every page this tab loads from now on, before the page's own scripts. */
function onEveryLoad(AwaitableWebpage $page, string $script): void
{
    $playwright = (fn () => $this->page)->call($page);
    $playwright->context()->addInitScript($script);
}

/** Switch the browser's prefers-color-scheme without a reload. */
function emulateScheme(AwaitableWebpage $page, string $scheme): void
{
    $playwright = (fn () => $this->page)->call($page);
    (fn () => $this->processVoidResponse(Client::instance()->execute($this->guid, 'emulateMedia', ['colorScheme' => $scheme])))->call($playwright);
}

it('turns the panel dark from the user menu and changes the chart colours with no chart code', function () {
    $page = signedInAt('/lathe');

    $page->assertScript(HTML_IS_DARK, false);
    $light = $page->script(CHART_ONE);

    chooseAppearance($page, 'Dark');

    $page->assertScript(HTML_IS_DARK, true)
        ->assertScript('localStorage.getItem("lathe-appearance")', 'dark');
    expect($page->script(CHART_ONE))->not->toBe('')->not->toBe($light);
    assertCleanPage($page);
});

it('has the dark class on html before the entry module starts after a reload', function () {
    $page = signedInAt('/lathe');
    chooseAppearance($page, 'Dark');

    onEveryLoad($page, <<<'JS'
        new MutationObserver((records, observer) => {
            for (const added of records.flatMap((record) => [...record.addedNodes])) {
                if (added.nodeName === 'SCRIPT' && added.type === 'module') {
                    window.__darkAtEntry = document.documentElement.classList.contains('dark');
                    observer.disconnect();
                }
            }
        }).observe(document, { childList: true, subtree: true });
        JS);
    $page->navigate('/lathe');

    $page->assertScript('window.__darkAtEntry', true)
        ->assertScript(HTML_IS_DARK, true);
    assertCleanPage($page);
});

it('follows the emulated colour scheme live in System mode', function () {
    $page = signedInAt('/lathe');
    chooseAppearance($page, 'System');

    $page->assertScript(HTML_IS_DARK, false);

    emulateScheme($page, 'dark');
    $page->assertScript(HTML_IS_DARK, true);

    emulateScheme($page, 'light');
    $page->assertScript(HTML_IS_DARK, false);
    assertCleanPage($page);
});

it('applies theme.appearance dark for a user with no stored choice', function () {
    config(['lathe.theme.appearance' => 'dark']);

    $page = signedInAt('/lathe');

    $page->assertScript('localStorage.getItem("lathe-appearance")', null)
        ->assertScript(HTML_IS_DARK, true);
    assertCleanPage($page);
});

it('uses the config default and still switches for the session when localStorage throws', function () {
    config(['lathe.theme.appearance' => 'dark']);
    $page = signedInAt('/lathe');

    onEveryLoad($page, <<<'JS'
        Object.defineProperty(window, 'localStorage', {
            get() {
                throw new DOMException('blocked', 'SecurityError');
            },
        });
        JS);
    $page->navigate('/lathe')->assertScript(HTML_IS_DARK, true);

    chooseAppearance($page, 'Light');
    $page->assertScript(HTML_IS_DARK, false);

    // The menu is still open after the pick.
    $page->assertScript('document.querySelector(\'[role="menuitemradio"][aria-checked="true"]\').textContent', 'Light');
    assertCleanPage($page);
});
