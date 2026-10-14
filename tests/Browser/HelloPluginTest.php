<?php

use Lathe\Plugins\PluginSetting;

require_once __DIR__.'/records.php';

it('opens the Hello Plugin settings from the sidebar, saves them and shows them after a reload', function () {
    $page = signedInAt('/lathe');

    $page->click('Settings')
        ->assertPathIs('/lathe/plugins/hello-plugin/settings')
        ->assertSee('Hello Plugin settings')
        ->assertSee('Appearance')
        ->assertSee('Shown at the top of the dashboard.');

    // An empty greeting stays on the page with the message under the field.
    $page->click('form button[type="submit"]')
        ->assertSee('The Greeting field is required.')
        ->assertAttribute('#setting-greeting', 'aria-invalid', 'true');

    $page->type('#setting-greeting', 'Hello there')
        ->select('#setting-accent', 'green')
        ->clear('#setting-max_items')
        ->type('#setting-max_items', '25')
        ->click('form button[type="submit"]')
        ->assertSee('Settings saved.');

    expect(PluginSetting::find('hello-plugin')?->data)->toMatchArray(['greeting' => 'Hello there', 'accent' => 'green', 'max_items' => 25]);

    $page->refresh()
        ->assertValue('#setting-greeting', 'Hello there')
        ->assertSelected('#setting-accent', 'green')
        ->assertValue('#setting-max_items', '25');
    assertCleanPage($page);
});
