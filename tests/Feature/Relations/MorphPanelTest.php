<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function commentedPost(): Post
{
    return Post::whereIn('id', Comment::where('commentable_type', Post::class)->pluck('commentable_id'))->orderBy('id')->firstOrFail();
}

it('lists a post\'s comments in its Comments panel without the Commentable column', function () {
    $post = commentedPost();

    $json = $this->getJson("/lathe/api/resources/posts/{$post->id}/comments?per_page=100")->assertOk()->json();

    expect(collect($json['rows'])->pluck('related_id')->sort()->values()->all())
        ->toBe(Comment::where('commentable_type', Post::class)->where('commentable_id', $post->id)->orderBy('id')->limit(100)->pluck('id')->all())
        ->and(collect($json['columns'])->pluck('key')->all())->not->toContain('commentable');
});

it('locks Commentable to the post under via and forces the pair the client sent', function () {
    $post = commentedPost();

    $field = collect($this->getJson("/lathe/api/resources/comments/creation-fields?via=posts/{$post->id}/comments")->assertOk()->json('fields'))->firstWhere('key', 'commentable');

    expect($field['readonly'])->toBeTrue()
        ->and($field['value'])->toMatchArray(['type' => 'posts', 'id' => $post->id]);

    $id = $this->postJson("/lathe/api/resources/comments?via=posts/{$post->id}/comments", [
        'body' => 'Under the post',
        'commentable' => ['type' => 'products', 'id' => 3],
    ])->assertCreated()->json('id');

    $comment = Comment::findOrFail($id);
    expect($comment->commentable_type)->toBe(Post::class)
        ->and($comment->commentable_id)->toBe($post->id);
});

it('creates a comment under a product the same way', function () {
    $id = $this->postJson('/lathe/api/resources/comments?via=products/2/comments', ['body' => 'On a product'])->assertCreated()->json('id');

    expect(Comment::findOrFail($id)->commentable_type)->toBe(Product::class)
        ->and(Comment::findOrFail($id)->commentable_id)->toBe(2);
});

it('refuses a comment under via for a user denied addComments', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->postJson('/lathe/api/resources/comments?via=products/2/comments', ['body' => 'Nope'])->assertForbidden();
});

it('attaches and detaches with a pivot value from both sides of a morph pivot', function () {
    $sale = Tag::where('name', 'sale')->firstOrFail();

    $this->postJson("/lathe/api/resources/tags/{$sale->id}/products", ['related_id' => 1, 'pivot' => ['position' => 3]])->assertCreated();
    expect(Product::findOrFail(1)->tags()->where('tags.id', $sale->id)->first()?->pivot->position)->toBe(3);

    $this->deleteJson("/lathe/api/resources/products/1/tags/{$sale->id}")->assertOk();
    expect(Product::findOrFail(1)->tags()->where('tags.id', $sale->id)->exists())->toBeFalse();

    $this->postJson('/lathe/api/resources/products/1/tags', ['related_id' => $sale->id, 'pivot' => ['position' => 4]])->assertCreated();
    $this->deleteJson("/lathe/api/resources/tags/{$sale->id}/products/1")->assertOk();
    expect(Product::findOrFail(1)->tags()->where('tags.id', $sale->id)->exists())->toBeFalse();
});
