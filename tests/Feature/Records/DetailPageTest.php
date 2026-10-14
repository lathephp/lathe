<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class ShopOrder extends Order
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function detailBreadcrumbs(LatheRequest $request): ?array
    {
        return [['label' => 'Shop', 'href' => '/shop'], ['label' => (string) $this->getModel()?->getAttribute('number'), 'href' => null]];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
});

it('shows an order with its fields, layout, abilities and default breadcrumbs', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Detail', false)
            ->where('resource.uriKey', 'orders')
            ->where('record.id', 12)
            ->where('record.title', 'ORD-00099454')
            ->where('record.subtitle', '')
            ->where('record.avatar', null)
            ->where('record.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['number', 'status', 'placed_at', 'created_at', 'customer', 'total_cents', 'items'])
            ->where('record.fields.0.label', 'Number')
            ->where('record.fields.0.value', 'ORD-00099454')
            ->where('record.fields.0.display', 'ORD-00099454')
            ->where('record.fields.0.readonly', false)
            ->where('record.layout.0.fields', ['number', 'status', 'placed_at', 'created_at'])
            ->where('record.abilities', ['view' => true, 'update' => true, 'delete' => true, 'replicate' => true, 'restore' => false, 'forceDelete' => false])
            ->where('record.trashed', false)
            ->where('breadcrumbs', [
                ['label' => 'Dashboard', 'href' => '/lathe'],
                ['label' => 'Orders', 'href' => '/lathe/resources/orders'],
                ['label' => '#12', 'href' => null],
            ])
            ->where('actions', [])
            ->where('widgets', []));
});

it('uses detailBreadcrumbs() in place of the default trail', function () {
    Lathe::registerResources(ShopOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/shop-orders/12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Detail', false)
            ->where('breadcrumbs', [['label' => 'Shop', 'href' => '/shop'], ['label' => 'ORD-00099454', 'href' => null]]));
});

it('hides the canSee order field from the editor', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Detail', false)
            ->where('record.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['number', 'status', 'placed_at', 'customer', 'total_cents', 'items'])
            ->where('record.abilities.update', false));
});

it('shows a trashed post with its fields and the trash abilities', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $post = Post::onlyTrashed()->orderBy('id')->firstOrFail();

    $this->get("/lathe/resources/posts/{$post->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Detail', false)
            ->where('record.trashed', true)
            ->where('record.title', $post->title)
            ->where('record.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['title', 'slug', 'status', 'cover', 'body', 'markdown_body', 'tags', 'comments'])
            ->where('record.abilities', ['view' => true, 'update' => true, 'delete' => false, 'replicate' => true, 'restore' => true, 'forceDelete' => true]));
});

it('shows the 404 page inside the shell for a missing order', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12')->assertOk();
    $this->get('/lathe/resources/orders/99999999')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Error', false)
            ->where('status', 404)
            ->where('shell', true));
});
