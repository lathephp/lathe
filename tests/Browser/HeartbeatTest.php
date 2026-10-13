<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

const HEARTBEAT_ROWS = "Object.fromEntries([...document.querySelectorAll('dl > div')].map((row) => [row.querySelector('dt').textContent, row.querySelector('dd').textContent]))";

function heartbeatPage(string $project)
{
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');
    $page->assertPathIs('/lathe');

    if (str_starts_with($project, 'phone')) {
        $page->click('[aria-label="Open menu"]');
    }

    return $page->click('#lathe-sidebar a[href$="/plugins/heartbeat"]')
        ->assertPathIs('/lathe/plugins/heartbeat')
        ->assertSee('connected')
        // The sheet slides away after the link; axe would read it half way.
        ->assertScript("matchMedia('(min-width: 768px)').matches || document.getElementById('lathe-sidebar').getBoundingClientRect().right <= 0");
}

it('shows the five status rows from the sidebar page', function (string $project) {
    $page = heartbeatPage($project);

    expect(array_keys($page->script(HEARTBEAT_ROWS)))->toBe(['Application', 'PHP', 'Laravel', 'Database', 'Checked at'])
        ->and($page->script(HEARTBEAT_ROWS)['Database'])->toBe('connected');

    $page->assertNoAccessibilityIssues();
    assertFitsViewport($page);
    assertCleanPage($page);
})->with(browserProjects());

it('pings and reloads the status with one React', function (string $project) {
    $page = heartbeatPage($project);
    $checkedAt = $page->script(HEARTBEAT_ROWS)['Checked at'];

    $page->press('Ping')
        ->assertSee('Heartbeat OK')
        ->assertScript(HEARTBEAT_ROWS."['Checked at'] > ".json_encode($checkedAt))
        // The react specifier's URL is the one copy the panel and the plugin share.
        ->assertScript(<<<'JS'
            (() => {
                const react = JSON.parse(document.querySelector('script[type="importmap"]').textContent).imports.react;
                return performance.getEntriesByType('resource').filter((entry) => new URL(entry.name).pathname === react).length;
            })()
            JS, 1);
    assertCleanPage($page);
})->with(browserProjects());

it('shows the failure when the status request fails', function (string $project) {
    $page = heartbeatPage($project);

    $default = DB::getDefaultConnection();
    config(['database.connections.stopped' => ['driver' => 'sqlite', 'database' => '/nonexistent/lathe.sqlite']]);
    DB::setDefaultConnection('stopped');
    // A fresh PHP process loads the signed-in user from the database; the in-process server would reuse it.
    Auth::forgetGuards();

    try {
        $page->press('Ping')
            ->assertSee('Heartbeat failed')
            ->assertScript(HEARTBEAT_ROWS."['Database']", 'down');
    } finally {
        DB::setDefaultConnection($default);
    }
})->with(browserProjects());
