<?php

require_once __DIR__.'/records.php';

it('has no accessibility issues on the Orders edit form', function (string $project) {
    $page = signedInAt('/lathe/resources/orders/12/edit', $project);

    $page->assertPresent('#field-number')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with(browserProjects());

it('has no accessibility issues on an open modal', function () {
    $page = signedInAt('/lathe/resources/orders/12/edit?tab=items');

    $page->click('#field-items li[data-repeater-row]:nth-child(1) [aria-label="Remove"]')
        ->assertSeeIn('[role="dialog"]', 'Remove this row?')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
});

// The Orders create page answers 403 for everyone (OrderPolicy has no create), so the
// Orders edit form and the Customers create form stand in for it.
it('fits the phone viewport with no horizontal page scroll on the record forms', function (string $project) {
    $page = signedInAt('/lathe/resources/orders/12/edit', $project);

    $page->assertPresent('#field-number');
    assertFitsViewport($page);

    $page->navigate('/lathe/resources/customers/new')
        ->assertPresent('#field-name');
    assertFitsViewport($page);
    assertCleanPage($page);
})->with(['phone-android', 'phone-ios']);

// No workbench Resource has a Code field, so the code editor is not on this page.
it('loads the Posts create form with the Tiptap and Markdown editors under the strict policy', function () {
    $page = signedInAt('/lathe/resources/posts/new');

    $page->assertPresent('#field-body[contenteditable="true"]')
        ->assertPresent('textarea#field-markdown_body')
        ->type('#field-markdown_body', '**Bold**')
        ->click('#field-markdown_body-preview')
        ->assertSourceHas('<strong>Bold</strong>');
    assertCleanPage($page);
})->skip(TIPTAP_CHUNK_THROWS.' Once it renders, Tiptap injects its base <style data-tiptap-style> without the nonce (injectCSS), and the strict policy blocks it twice.');
