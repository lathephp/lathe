<?php

it('shows a 404 in the shell with a link back to the dashboard', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')
        ->navigate('/lathe/nothing')
        ->assertSee('Page not found')
        ->assertPresent('#lathe-sidebar')
        ->assertNoAccessibilityIssues()
        ->click('Back to dashboard')
        ->assertPathIs('/lathe')
        ->assertSee('No widgets yet');
    assertCleanPage($page);
})->with(browserProjects());

it('shows a signed-out visitor the 404 full screen', function (string $project) {
    $page = latheVisit('/lathe/nothing', $project);

    $page->assertSee('Page not found')
        ->assertSee('Back to dashboard')
        ->assertMissing('#lathe-sidebar')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with(browserProjects());
