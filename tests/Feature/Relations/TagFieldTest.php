<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lathe\Events\ResourceAttached;
use Lathe\Events\ResourceDetached;
use Lathe\Field\Field;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Customer;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\App\Policies\PostPolicy;
use Workbench\Database\Seeders\DemoSeeder;

class TagLimitPosts extends Workbench\App\Lathe\Resources\Post
{
    public static function uriKey(): string
    {
        return 'tag-limit-posts';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->idListMax(2);
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function postPayload(Post $post, array $tags): array
{
    return [
        'title' => $post->title,
        'slug' => $post->slug,
        'status' => $post->status,
        'body' => $post->body,
        'markdown_body' => $post->markdown_body,
        'tags' => $tags,
    ];
}

it('syncs the posted tag ids without pivot data', function () {
    $post = Post::orderBy('id')->firstOrFail();
    $ids = Tag::orderBy('id')->limit(2)->pluck('id')->all();

    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, [['value' => $ids[0]], $ids[1]]))->assertOk();

    expect($post->tags()->orderBy('tags.id')->pluck('tags.id')->all())->toBe($ids);

    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, [$ids[1]]))->assertOk();

    expect($post->tags()->pluck('tags.id')->all())->toBe([$ids[1]]);
});

it('sends the tags as picker items and preloads the 50 tags', function () {
    $post = Post::orderBy('id')->firstOrFail();
    $post->tags()->sync(Tag::orderBy('id')->limit(2)->pluck('id'));

    $field = collect($this->getJson("/lathe/api/resources/posts/{$post->id}/update-fields")->assertOk()->json('fields'))->firstWhere('key', 'tags');

    expect($field['value'])->toHaveCount(2)
        ->and($field['value'][0])->toHaveKeys(['value', 'display'])
        ->and($field['preload'])->toBeTrue()
        ->and($field['options'])->toHaveCount(50)
        ->and($field['withPreview'])->toBeTrue();
});

it('uses the search picker for a table over 100 rows', function () {
    $field = Field::tag('Buyers', 'customer', Customer::class)->preload()->ownedBy(OrderResource::class);

    expect($field->jsonSerialize()['preload'])->toBeFalse()
        ->and($field->jsonSerialize())->not->toHaveKey('options');
});

it('refuses more ids than idListMax and ids the picker does not offer', function () {
    Lathe::registerResources(TagLimitPosts::class);
    $post = Post::orderBy('id')->firstOrFail();

    $this->putJson("/lathe/api/resources/tag-limit-posts/{$post->id}", postPayload($post, Tag::orderBy('id')->limit(3)->pluck('id')->all()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');

    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, [99999999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
});

it('caps the list at limits.id_list_max', function () {
    $post = Post::orderBy('id')->firstOrFail();

    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, range(1, 1001)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
});

class NoDetachPostPolicy extends PostPolicy
{
    public function detachTags(User $user, Post $post, array $ids): bool
    {
        return false;
    }
}

it('refuses to remove tags without detachTags, an empty list included', function () {
    Gate::policy(Post::class, NoDetachPostPolicy::class);
    $post = Post::orderBy('id')->firstOrFail();
    $post->tags()->sync([1, 2]);

    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, []))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, [1]))
        ->assertUnprocessable();

    expect($post->tags()->count())->toBe(2);

    // Adding without removing needs attach only.
    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, [1, 2, 3]))->assertOk();
    expect($post->tags()->count())->toBe(3);
});

it('fires the attach and detach events of a tag sync and leaves the tags alone when the field is not sent', function () {
    Event::fake([ResourceAttached::class, ResourceDetached::class]);
    $post = Post::orderBy('id')->firstOrFail();
    $post->tags()->sync([1, 2]);

    $this->putJson("/lathe/api/resources/posts/{$post->id}", postPayload($post, [2, 3]))->assertOk();

    Event::assertDispatched(ResourceAttached::class, fn ($event) => $event->ids === [3] && $event->relationship === 'tags');
    Event::assertDispatched(ResourceDetached::class, fn ($event) => $event->ids === [1]);

    $payload = postPayload($post, []);
    unset($payload['tags']);
    $this->putJson("/lathe/api/resources/posts/{$post->id}", $payload)->assertOk();

    expect($post->tags()->orderBy('tags.id')->pluck('tags.id')->all())->toBe([2, 3]);
});
