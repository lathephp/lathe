<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
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

it('refuses an editor tying an order item to an order from the item side', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->postJson('/lathe/api/resources/order-items', [
        'order' => ['value' => 12],
        'product' => ['value' => Product::orderBy('id')->value('id')],
        'quantity' => 1,
        'price_cents' => 1.00,
    ])->assertUnprocessable()->assertJsonValidationErrors('order');

    expect(Order::findOrFail(12)->items()->where('quantity', 1)->where('price_cents', 100)->exists())->toBeFalse();
});

it('tells a panel whether the related Resource searches anything', function () {
    expect($this->getJson('/lathe/api/resources/orders/12/items')->assertOk()->json('searchable'))->toBeFalse()
        ->and($this->getJson('/lathe/api/resources/customers/12/orders')->assertOk()->json('searchable'))->toBeTrue();
});

it('sends the detach flag beside add and attach', function () {
    expect($this->getJson('/lathe/api/resources/products/relate-authorization?resourceId=1')->assertOk()->json('tags'))
        ->toBe(['add' => false, 'attach' => true, 'attachAny' => true, 'detach' => true]);

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    expect($this->getJson('/lathe/api/resources/products/relate-authorization?resourceId=1')->assertOk()->json('tags.detach'))->toBeFalse();
});

it('keeps a pivot position a whole number', function () {
    $tag = Product::findOrFail(2)->tags()->orderBy('tags.id')->firstOrFail();

    foreach (['1.5', '99999999999999999999'] as $position) {
        $this->putJson("/lathe/api/resources/products/2/tags/{$tag->id}/pivot", ['pivot' => ['position' => $position]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pivot.position');
    }
});

it('lists a comment\'s body in a Comments panel and counts and folds the Items panel', function () {
    $post = Comment::where('commentable_type', Post::class)->value('commentable_id');

    expect(collect($this->getJson("/lathe/api/resources/posts/{$post}/comments")->assertOk()->json('columns'))->pluck('key')->all())->toContain('body');

    expect(Workbench\App\Lathe\Resources\Order::make()->fieldsFor(FieldContext::Detail, LatheRequest::createFrom(request()))->first(fn ($field) => $field->attribute() === 'items')?->jsonSerialize())
        ->toMatchArray(['withCount' => true, 'collapsible' => true]);
});
