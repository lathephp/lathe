<?php

use Workbench\App\Models\Comment;

require_once __DIR__.'/records.php';

// PostPolicy refuses delete, so the soft-deleting Posts offer no Delete Selected; Comments may be deleted.
it('deletes two ticked comments from the Delete menu after the confirm', function () {
    $page = signedInAt('/lathe/resources/comments')->assertPresent('table tbody > tr');
    $ids = $page->script("JSON.parse(document.querySelector('script[data-page]').textContent).props.table.rows.slice(0, 2).map((row) => row.id)");
    $labels = $page->script("[...document.querySelectorAll('table tbody > tr input[aria-label^=\"Select \"]')].slice(0, 2).map((box) => box.getAttribute('aria-label'))");
    $count = Comment::count();

    expect($ids)->toHaveCount(2);

    foreach ($labels as $label) {
        $page->click('[aria-label="'.$label.'"]');
    }

    $page->click('button[aria-haspopup="menu"]:text-is("Delete")')
        ->assertPresent('[role="menu"][aria-label="Delete"]');

    expect($page->script("[...document.querySelectorAll('[role=\"menu\"][aria-label=\"Delete\"] [role=\"menuitem\"]')].map((item) => item.textContent)"))->toBe(['Delete Selected']);

    $page->click('[role="menuitem"]:text-is("Delete Selected")')
        ->assertSeeIn('[role="alertdialog"] h2', 'Delete 2 resource(s)?')
        ->click('[role="alertdialog"] button:text-is("Delete Selected")')
        ->assertSee('2 resource(s) deleted.')
        ->assertNotPresent('[role="alertdialog"]')
        ->assertNotPresent('[aria-label="'.$labels[0].'"]')
        ->assertNotPresent('[aria-label="'.$labels[1].'"]')
        ->assertPresent('table tbody > tr');

    expect(Comment::whereKey($ids)->count())->toBe(0)
        ->and(Comment::count())->toBe($count - 2);
    assertCleanPage($page);
});
