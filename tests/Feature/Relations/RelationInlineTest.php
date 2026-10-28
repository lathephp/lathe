<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Comment as CommentResource;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Lathe\Resources\Post;
use Workbench\App\Lathe\Resources\Product;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post as PostModel;
use Workbench\App\Models\User;
use Workbench\App\Policies\CommentPolicy;
use Workbench\Database\Seeders\DemoSeeder;

class InlineCustomerOrders extends OrderResource
{
    public static function uriKey(): string
    {
        return 'inline-customer-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->board(null);
    }

    public function fieldsForIndex(LatheRequest $request): array
    {
        return [Field::text('Number'), Field::belongsTo('Customer')->inline()];
    }
}

class InlineCommentables extends CommentResource
{
    public static function uriKey(): string
    {
        return 'inline-commentables';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function fieldsForIndex(LatheRequest $request): array
    {
        return [Field::textarea('Body'), Field::morphTo('Commentable')->types([Post::class, Product::class])->inline()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Gate::policy(Comment::class, InlineCommentPolicy::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(InlineCustomerOrders::class, InlineCommentables::class);
});

class InlineCommentPolicy extends CommentPolicy
{
    public function inlineEdit(User $user, Comment $comment): bool
    {
        return true;
    }
}

it('commits a belongsTo cell and answers the fresh cells of the row', function () {
    $order = Order::findOrFail(12);
    $other = Customer::whereKeyNot($order->customer_id)->orderBy('id')->firstOrFail();

    $json = $this->patchJson('/lathe/api/resources/inline-customer-orders/12/field/customer', ['value' => $other->id])->assertOk()->json();

    expect($order->fresh()?->customer_id)->toBe($other->id)
        ->and($json['fields']['customer']['value']['value'])->toBe($other->id)
        ->and($json['fields']['customer']['display'])->toBe($other->name);
});

it('runs the Relatable rule on an inline belongsTo', function () {
    $this->patchJson('/lathe/api/resources/inline-customer-orders/12/field/customer', ['value' => 99999999])
        ->assertUnprocessable();
});

it('commits a morphTo cell with its type', function () {
    $comment = Comment::orderBy('id')->firstOrFail();
    $post = PostModel::orderBy('id')->firstOrFail();

    $json = $this->patchJson("/lathe/api/resources/inline-commentables/{$comment->id}/field/commentable", ['value' => $post->id, 'type' => 'posts'])->assertOk()->json();

    expect($comment->fresh()?->commentable_type)->toBe(PostModel::class)
        ->and($json['fields']['commentable']['value']['type'])->toBe('posts')
        ->and($json['fields']['commentable']['display'])->toBe($post->title);
});
