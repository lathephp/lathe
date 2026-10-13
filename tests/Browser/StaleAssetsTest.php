<?php

use Illuminate\Support\Facades\File;
use Lathe\Lathe;

/**
 * A copy of the built panel whose entry file carries another hash, as a rebuild leaves it.
 */
function rebuiltDist(string $hash): string
{
    $dist = Lathe::assets()->distPath();
    $copy = sys_get_temp_dir().'/lathe-rebuilt-'.getmypid();
    File::deleteDirectory($copy);
    File::copyDirectory($dist, $copy);

    $old = Lathe::assets()->hash();
    rename("{$copy}/assets/app-{$old}.js", "{$copy}/assets/app-{$hash}.js");
    file_put_contents("{$copy}/manifest.json", str_replace("app-{$old}.js", "app-{$hash}.js", (string) file_get_contents("{$copy}/manifest.json")));

    return $copy;
}

it('asks for a reload after the assets change, once', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');
    $page->assertPathIs('/lathe');

    $copy = rebuiltDist('zzzzzzzz');
    Lathe::assets()->useDistPath($copy);

    try {
        if (str_starts_with($project, 'phone')) {
            $page->click('[aria-label="Open menu"]');
        }

        $page->click('#lathe-sidebar a[href$="/resources/orders"]')
            ->assertSee('Lathe was updated')
            ->assertSee('If this keeps happening, run php artisan lathe:assets --force')
            ->click('Reload')
            ->assertSee('Showing 1 to 25 of 10,000')
            ->assertPresent('script[type="module"][src*="/assets/zzzzzzzz/app-zzzzzzzz.js"]')
            ->assertDontSee('Lathe was updated');

        if (str_starts_with($project, 'phone')) {
            $page->click('[aria-label="Open menu"]');
        }

        $page->click('#lathe-sidebar a[href="/lathe"]')
            ->assertSee('No widgets yet')
            ->assertDontSee('Lathe was updated');
        assertCleanPage($page);
    } finally {
        File::deleteDirectory($copy);
    }
})->with(browserProjects());
