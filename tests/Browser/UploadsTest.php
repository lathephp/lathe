<?php

use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\User;

require_once __DIR__.'/records.php';

/** A script for the users table row that holds the given name. */
function userRow(string $name): string
{
    return '[...document.querySelectorAll("table tbody tr")].find((row) => row.textContent.includes('.json_encode($name).'))';
}

function seedEditorAvatar(): User
{
    servePublicDisk();
    Storage::disk('public')->put('avatars/editor.png', (string) file_get_contents(uploadFixture('avatar-3mb.png')));
    $editor = User::where('email', 'editor@lathephp.com')->sole();
    $editor->forceFill(['avatar' => 'avatars/editor.png'])->save();

    return $editor;
}

it('uploads an avatar with a progress bar and shows the thumbnail in the list row', function () {
    servePublicDisk();
    $editor = User::where('email', 'editor@lathephp.com')->sole();
    $page = signedInAt("/lathe/resources/users/{$editor->id}/edit");

    $page->attach('#field-avatar', uploadFixture('avatar-3mb.png'))
        ->assertPresent('#field-avatar ~ div [role="progressbar"]')
        ->assertScript("document.querySelector('[role=\"progressbar\"] + span')?.textContent", 'Uploaded')
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/users/{$editor->id}")
        ->navigate('/lathe/resources/users')
        ->assertScript(userRow('Editor User').'?.querySelector("img")?.naturalWidth', 1024);

    expect($editor->fresh()?->getAttribute('avatar_name'))->toBe('avatar-3mb.png')
        ->and(Storage::disk('public')->exists((string) $editor->fresh()?->getAttribute('avatar')))->toBeTrue();
    assertCleanPage($page);
});

it('refuses a 12 MB file before any chunk is sent', function () {
    $editor = User::where('email', 'editor@lathephp.com')->sole();
    $page = signedInAt("/lathe/resources/users/{$editor->id}/edit");

    $page->attach('#field-avatar', uploadFixture('file-12mb.bin'))
        ->assertSeeIn('[role="alert"]', 'File exceeds the 5 MB limit')
        ->assertNotPresent('[role="progressbar"]')
        ->assertScript(requestCount('/chunks/'), 0)
        ->assertScript(requestCount('/uploads/avatar'), 0);
    assertCleanPage($page);
});

it('lists users with a rounded 32 px avatar', function (string $project) {
    seedEditorAvatar();
    $page = signedInAt('/lathe/resources/users', $project);

    $page->assertScript(userRow('Editor User').'?.querySelector("img")?.naturalWidth', 1024)
        ->assertScript(userRow('Editor User').'.querySelector("img").getBoundingClientRect().width', 32)
        ->assertScript(userRow('Editor User').'.querySelector("img").classList.contains("rounded-full")');
    assertCleanPage($page);
})->with(browserProjects());

it('lists Admin User with the initials AU')
    ->todo(note: 'An avatar field with no file draws the image placeholder in its index cell, and index rows carry no Resource avatar to take the initials from.');

it('shows the editor a 128 px rounded avatar on the detail page with no download link', function () {
    $editor = seedEditorAvatar();
    $page = signedInAt("/lathe/resources/users/{$editor->id}");

    $page->assertScript('document.querySelector("main section img")?.naturalWidth', 1024)
        ->assertScript('document.querySelector("main section img").getBoundingClientRect().width', 128)
        ->assertScript('document.querySelector("main section img").classList.contains("rounded-full")')
        ->assertNotPresent('main a[download]')
        ->assertNotPresent('main a[href*="/download/"]');
    assertCleanPage($page);
});
