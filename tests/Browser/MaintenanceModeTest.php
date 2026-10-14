<?php

afterEach(function () {
    $this->app->maintenanceMode()->deactivate();
});

it('shows the maintenance page to a first visit while the app is down', function (string $project) {
    $this->app->maintenanceMode()->activate([]);

    latheVisit('/lathe/login', $project)
        ->assertSee('Service unavailable')
        ->assertDontSee('Lathe was updated');
})->with(browserProjects());

it('shows the maintenance page to a signed-in visit while the app is down', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');
    $page->assertPathIs('/lathe');

    $this->app->maintenanceMode()->activate([]);

    if (str_starts_with($project, 'phone')) {
        $page->click('[aria-label="Open menu"]');
    }

    $page->click('#lathe-sidebar a[href$="/resources/orders"]')
        ->assertSee('Service unavailable')
        ->assertDontSee('Lathe was updated');
})->with(browserProjects());
