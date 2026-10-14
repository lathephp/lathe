<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Resources\ResourceRegistry;
use Workbench\App\Lathe\Resources\Category;
use Workbench\App\Lathe\Resources\Comment;
use Workbench\App\Lathe\Resources\OrderItem;
use Workbench\App\Lathe\Resources\Tag;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag as TagModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertExitCode(0);
    $this->seed(DatabaseSeeder::class);
});

it('seeds 50 tags with one named sale that product 1 does not carry', function () {
    expect(TagModel::count())->toBe(50)
        ->and(TagModel::where('name', 'sale')->exists())->toBeTrue()
        ->and(Product::find(1)->tags()->where('name', 'sale')->exists())->toBeFalse();
});

it('tags the first 100 products twice with integer positions', function () {
    $twice = DB::table('taggables')->where('taggable_type', Product::class)
        ->groupBy('taggable_id')->havingRaw('count(*) = 2')->pluck('taggable_id');

    expect($twice->count())->toBeGreaterThanOrEqual(100)
        ->and(Product::find(2)->tags->first()->pivot->position)->toBeInt();
});

it('registers the relation milestone resources by uri key', function () {
    $registry = app(ResourceRegistry::class);

    expect($registry->forKey('categories'))->toBe(Category::class)
        ->and($registry->forKey('tags'))->toBe(Tag::class)
        ->and($registry->forKey('order-items'))->toBe(OrderItem::class)
        ->and($registry->forKey('comments'))->toBe(Comment::class);
});

it('lets the admin and not the editor use the relation abilities', function () {
    $order = Order::first();
    $product = Product::first();

    foreach (['admin@lathephp.com' => true, 'editor@lathephp.com' => false] as $email => $allowed) {
        $gate = Gate::forUser(User::where('email', $email)->firstOrFail());

        expect($gate->allows('create', Order::class))->toBe($allowed)
            ->and($gate->allows('addCustomer', $order))->toBe($allowed)
            ->and($gate->allows('addItems', $order))->toBe($allowed)
            ->and($gate->allows('attachTags', [$product, [1]]))->toBe($allowed)
            ->and($gate->allows('detachTags', [$product, [1]]))->toBe($allowed)
            ->and($gate->allows('attachAnyTags', $product))->toBe($allowed)
            ->and($gate->allows('addComments', $product))->toBe($allowed);
    }
});
