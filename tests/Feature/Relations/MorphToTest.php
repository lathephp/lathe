<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function commentOn(string $class): Comment
{
    return Comment::where('commentable_type', $class)->orderBy('id')->firstOrFail();
}

it('sends a morphTo value as the type uriKey, the id and the title', function () {
    $comment = commentOn(Product::class);
    $product = Product::findOrFail($comment->commentable_id);

    $field = collect($this->getJson("/lathe/api/resources/comments/{$comment->id}/update-fields")->assertOk()->json('fields'))->firstWhere('key', 'commentable');

    expect($field['value'])->toBe(['type' => 'products', 'id' => $product->id, 'display' => $product->name, 'href' => "/lathe/resources/products/{$product->id}"])
        ->and(array_column($field['types'], 'value'))->toBe(['posts', 'products']);
});

it('writes the morph alias and key from a uriKey type', function () {
    $comment = commentOn(Product::class);
    $post = Post::orderBy('id')->firstOrFail();

    $this->putJson("/lathe/api/resources/comments/{$comment->id}", ['body' => 'Moved', 'commentable' => ['type' => 'posts', 'id' => $post->id]])->assertOk();

    $comment->refresh();
    expect($comment->commentable_type)->toBe(Post::class)
        ->and($comment->commentable_id)->toBe($post->id);
});

it('refuses a class name or a type outside types()', function (mixed $type) {
    $comment = commentOn(Product::class);

    $this->putJson("/lathe/api/resources/comments/{$comment->id}", ['body' => 'Moved', 'commentable' => ['type' => $type, 'id' => 1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('commentable');

    expect($comment->fresh()?->commentable_type)->toBe(Product::class);
})->with([
    'class name' => [Post::class],
    'other resource' => ['orders'],
]);

it('looks up the records of the chosen type and 404s an unknown one', function () {
    $json = $this->getJson('/lathe/api/resources/comments/associatable/commentable?type=products&q=a')->assertOk()->json();

    foreach ($json['items'] as $item) {
        expect(Product::whereKey($item['value'])->exists())->toBeTrue();
    }

    $this->getJson('/lathe/api/resources/comments/associatable/commentable?type=orders')->assertNotFound();
    $this->getJson('/lathe/api/resources/comments/associatable/commentable')->assertNotFound();
});

it('answers 404 for a current record outside the type\'s scope', function () {
    $trashed = Post::onlyTrashed()->orderBy('id')->firstOrFail();

    $this->getJson("/lathe/api/resources/comments/associatable/commentable?type=posts&current={$trashed->id}")->assertNotFound();
    $this->getJson('/lathe/api/resources/comments/associatable/commentable?type=posts&current='.Post::orderBy('id')->value('id'))->assertOk();
});

it('still names a trashed post on the comments index, in a fixed number of queries', function () {
    $trashed = Post::onlyTrashed()->orderBy('id')->firstOrFail();
    $comment = commentOn(Product::class);
    $comment->update(['commentable_type' => Post::class, 'commentable_id' => $trashed->id]);

    $field = collect($this->getJson("/lathe/api/resources/comments/{$comment->id}/update-fields")->assertOk()->json('fields'))->firstWhere('key', 'commentable');
    expect($field['value']['display'])->toBe($trashed->title);

    $count = function (int $perPage): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->getJson("/lathe/api/resources/comments?per_page={$perPage}")->assertOk();

        return $queries;
    };

    expect($count(25))->toBe($count(100));
});

it('saves a comment on a trashed post, kept or newly picked', function () {
    $trashed = Post::onlyTrashed()->orderBy('id')->firstOrFail();
    $comment = commentOn(Product::class);
    $comment->update(['commentable_type' => Post::class, 'commentable_id' => $trashed->id]);

    // Keeping the trashed post the comment already has.
    $this->putJson("/lathe/api/resources/comments/{$comment->id}", ['body' => 'Kept', 'commentable' => ['type' => 'posts', 'id' => $trashed->id]])->assertOk();

    // Picking a trashed post anew: Commentable shows trashed records by default.
    $other = commentOn(Product::class);
    $this->putJson("/lathe/api/resources/comments/{$other->id}", ['body' => 'Moved', 'commentable' => ['type' => 'posts', 'id' => $trashed->id]])->assertOk();

    expect($other->fresh()?->commentable_id)->toBe($trashed->id);
});
