<?php

use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Post;

require_once __DIR__.'/records.php';

const SCRIPT_BODY = "**Bold** words\n\n<script>alert(1)</script>";

/** True when the record's detail region holds no script element and no alert(1) text. */
const DETAIL_IS_SCRIPT_FREE = "!document.querySelector('main').innerHTML.includes('<script') && !document.querySelector('main').textContent.includes('alert(1)')";

function createPostWithScript(string $project, ?string $cover = null): AwaitableWebpage
{
    $page = signedInAt('/lathe/resources/posts', $project);
    recordDialogs($page);

    $page->click('a[href$="/resources/posts/new"]')
        ->assertPathIs('/lathe/resources/posts/new')
        ->type('#field-title', 'Hello World')
        ->fill('#field-body', 'The body.')
        ->fill('#field-markdown_body', SCRIPT_BODY);

    if ($cover !== null) {
        $page->attach('#field-cover', $cover)
            ->assertScript("document.querySelector('[role=\"progressbar\"] + span')?.textContent", 'Uploaded');
    }

    $page->click('form button[type="submit"]');

    return $page->assertScript('/^\/lathe\/resources\/posts\/\d+$/.test(location.pathname)')
        ->assertScript("document.querySelector('main h1')?.textContent", 'Hello World');
}

it('creates a post with a script in the body and an image, shows it safely, then changes its status inline', function (string $project) {
    servePublicDisk();

    $page = createPostWithScript($project, uploadFixture('avatar-3mb.png'));
    $post = Post::where('title', 'Hello World')->sole();

    $page->assertSourceHas('<strong>Bold</strong>')
        ->assertScript(DETAIL_IS_SCRIPT_FREE)
        // The Cover image loads from the public disk.
        ->assertScript("[...document.querySelectorAll('main img')].some((img) => img.complete && img.naturalWidth === 1024)")
        ->assertScript('window.__dialogs', []);

    expect($post->markdown_body)->toBe(SCRIPT_BODY)
        ->and($post->cover)->toBeString()->not->toBe('');

    $page->navigate('/lathe/resources/posts')
        ->assertSeeIn('table tbody tr:first-child', 'Hello World')
        ->select('table tbody tr:first-child select[aria-label="Status"]', 'published')
        ->assertScript("document.querySelector('table tbody tr:first-child select[aria-label=\"Status\"]').disabled === false");

    expect($post->fresh()?->status)->toBe('published');

    $page->refresh()
        ->assertSelected('table tbody tr:first-child select[aria-label="Status"]', 'published')
        ->assertScript('window.__dialogs', []);
    assertCleanPage($page);
})->with(browserProjects());

it('removes the script from the markdown body on the detail page and keeps the typed source', function () {
    $page = createPostWithScript('desktop-chrome');
    $post = Post::where('title', 'Hello World')->sole();

    $page->assertScript(DETAIL_IS_SCRIPT_FREE)
        ->assertScript('window.__dialogs', []);

    // A full load reads the detail response itself: the page data holds no script either.
    $page->refresh()
        ->assertScript("document.querySelector('main h1')?.textContent", 'Hello World')
        ->assertScript("!document.documentElement.outerHTML.includes('alert(1)')")
        ->assertScript(DETAIL_IS_SCRIPT_FREE)
        ->assertScript('window.__dialogs', []);

    expect($post->markdown_body)->toBe(SCRIPT_BODY);
    assertCleanPage($page);
});
