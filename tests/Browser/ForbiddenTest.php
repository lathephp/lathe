<?php

it('shows the guest a 403 in the shell with a way to sign out', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'guest@lathephp.com');

    $page->assertPathIs('/lathe')
        ->assertSee('Forbidden')
        ->assertSee('You do not have permission to open this page.')
        ->assertPresent('#lathe-sidebar')
        ->assertNoAccessibilityIssues()
        ->click('Sign out')
        ->assertPathIs('/lathe/login')
        ->assertSee('Remember me');
    assertCleanPage($page);
})->with(browserProjects());
