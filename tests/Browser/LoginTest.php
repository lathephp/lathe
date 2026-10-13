<?php

it('signs the admin in from the login page', function (string $project) {
    $page = latheVisit('/lathe/login', $project);

    $page->assertSee('Email')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('Sign in')
        ->assertDontSee('Forgot your password?')
        ->assertNoAccessibilityIssues();

    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')
        ->assertSee('No widgets yet')
        ->assertNoAccessibilityIssues();
    assertFitsViewport($page);
    assertCleanPage($page);
})->with(browserProjects());

it('shows a wrong password under the field', function (string $project) {
    $page = latheVisit('/lathe/login', $project);

    signInThroughForm($page, 'admin@lathephp.com', 'wrong-password');

    $page->assertPathIs('/lathe/login')
        ->assertSeeIn('#email-error', 'These credentials do not match our records.');
    assertCleanPage($page);
})->with(browserProjects());

it('sends a signed-out visitor to login and back to the page asked for', function (string $project) {
    $page = latheVisit('/lathe/resources/orders', $project);

    $page->assertPathIs('/lathe/login');

    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe/resources/orders')
        ->assertSee('Showing 1 to 25 of 10,000');
    assertCleanPage($page);
})->with(browserProjects());

it('logs out from the user menu', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    if (str_starts_with($project, 'phone')) {
        $page->click('[aria-label="Open menu"]');
    }

    $page->click('Admin User')
        ->assertSee('admin@lathephp.com')
        ->assertNoAccessibilityIssues()
        ->click('Log out')
        ->assertPathIs('/lathe/login')
        ->assertSee('Sign in');
    assertCleanPage($page);
})->with(browserProjects());
