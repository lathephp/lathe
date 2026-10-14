<?php

use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;

require_once __DIR__.'/records.php';

it('points a comment at a product picked after choosing the Product type', function () {
    $comment = Comment::where('commentable_type', Post::class)->orderBy('id')->firstOrFail();
    $product = Product::orderBy('id')->firstOrFail();

    $page = signedInAt("/lathe/resources/comments/{$comment->id}");

    $page->click("a[href$=\"/resources/comments/{$comment->id}/edit\"]")
        ->assertPathIs("/lathe/resources/comments/{$comment->id}/edit")
        ->assertScript('() => [...document.querySelectorAll("#field-commentable option")].map((option) => option.textContent).join("|")', '—|Post|Product')
        ->select('#field-commentable', 'products')
        // The field is not searchable, so it starts as a select; a first page of products with
        // more behind it turns that select into the searchable picker.
        ->click('#field-commentable-record')
        ->assertAttribute('#field-commentable-record', 'aria-haspopup', 'listbox')
        ->click('#field-commentable-record')
        ->assertPresent('[role="listbox"] [role="option"]')
        ->type('input[aria-controls="field-commentable-record-listbox"]', $product->name)
        // The picker waits its 500 ms debounce before it asks again.
        ->wait(1.5)
        ->assertSeeIn('[role="listbox"] [role="option"]:first-child', $product->name)
        ->click('[role="listbox"] [role="option"]:first-child')
        ->assertSeeIn('#field-commentable-record', $product->name)
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/comments/{$comment->id}")
        ->assertSeeIn("a[href=\"/lathe/resources/products/{$product->id}\"]", $product->name);

    $comment->refresh();
    expect($comment->commentable_type)->toBe(Product::class)
        ->and($comment->commentable_id)->toBe($product->id);
    assertCleanPage($page);
});
