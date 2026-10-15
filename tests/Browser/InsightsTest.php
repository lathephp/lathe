<?php

require_once __DIR__.'/records.php';

it('finds Overview under Insights in the palette and opens the page with the viewer name', function () {
    $page = signedInAt('/lathe');

    $page->keys('#app', modKey($page, 'k'))
        ->type('[role="combobox"]', 'overview')
        ->assertPresent('[role="group"][aria-label="Insights"]')
        ->assertCount('[role="group"][aria-label="Insights"] [role="option"]', 1)
        ->assertSeeIn('[role="group"][aria-label="Insights"] [role="option"]', 'Overview')
        ->keys('[role="combobox"]', 'Enter')
        ->assertPathIs('/lathe/plugins/insights')
        ->waitForText('Server time')
        ->assertSeeIn('main section > dl', 'Admin User');
    assertCleanPage($page);
});
