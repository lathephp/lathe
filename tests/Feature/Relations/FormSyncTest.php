<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Lathe\Events\ResourceAttached;
use Lathe\Events\ResourceDetached;
use Lathe\Events\ResourceUpdated;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Product as ProductResource;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class FormTagProducts extends ProductResource
{
    public static function uriKey(): string
    {
        return 'form-tag-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
            Field::morphToMany('Tags')->pivotFields([Field::number('Position')->min(0)])->showOnUpdating(),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(FormTagProducts::class);
    $product = Product::findOrFail(1);
    $product->tags()->sync([5 => ['position' => 1], 7 => ['position' => 2]]);
});

it('syncs the posted ids and pivot values after the save, with one event per change', function () {
    Event::fake([ResourceAttached::class, ResourceDetached::class]);

    $this->putJson('/lathe/api/resources/form-tag-products/1', [
        'name' => 'Renamed',
        'tags' => [3, 5],
        'tags_pivot' => ['3' => ['position' => 4], '5' => ['position' => 1]],
    ])->assertOk();

    expect(Product::findOrFail(1)->tags()->orderBy('tags.id')->pluck('tags.id')->all())->toBe([3, 5])
        ->and(Product::findOrFail(1)->tags()->where('tags.id', 3)->first()?->pivot->position)->toBe(4);

    Event::assertDispatchedTimes(ResourceAttached::class, 1);
    Event::assertDispatched(ResourceAttached::class, fn (ResourceAttached $event) => $event->ids === [3] && $event->relationship === 'tags');
    Event::assertDispatched(ResourceDetached::class, fn (ResourceDetached $event) => $event->ids === [7]);
});

it('leaves the pivot unchanged when the save rolls back', function () {
    Event::listen(ResourceUpdated::class, fn () => throw new RuntimeException('no'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->putJson('/lathe/api/resources/form-tag-products/1', ['name' => 'Renamed', 'tags' => [3]]))->toThrow(RuntimeException::class)
        ->and(Product::findOrFail(1)->tags()->orderBy('tags.id')->pluck('tags.id')->all())->toBe([5, 7]);
});

it('lists the declared pivot actions on the panel and adds no pivot-action route', function () {
    expect($this->getJson('/lathe/api/resources/products/2/tags')->assertOk()->json('pivotActions.*.uriKey'))->toBe(['move-to-first'])
        ->and(collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'pivot-action'))->all())->toBe([]);
});
