<?php

it('keeps the panel up when the Heartbeat module is missing', function (string $project) {
    $module = dirname(__DIR__, 2).'/workbench/lathe-components/Heartbeat/dist/heartbeat.js';
    rename($module, $module.'.renamed');

    try {
        $page = latheVisit('/lathe/login', $project);
        signInThroughForm($page, 'admin@lathephp.com');

        $page->assertPathIs('/lathe')
            ->assertSee('No widgets yet')
            ->assertSee('A plugin failed to load. The rest of the panel works.')
            ->assertCount('[role="alert"]', 1)
            ->assertNoJavaScriptErrors();

        expect($page->script('window.__lathe.errors'))->toBe(['[lathe] plugin "heartbeat" failed to load'])
            ->and($page->script('window.__lathe.csp'))->toBe([]);
    } finally {
        rename($module.'.renamed', $module);
    }
})->with(browserProjects());
