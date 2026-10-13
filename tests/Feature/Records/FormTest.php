<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Layout\Section;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Product as ProductModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Counts the closures of the fields an ?only= request leaves out. */
class FormClosureLog
{
    public static int $calls = 0;
}

class Product extends Resource
{
    public static function uriKey(): string
    {
        return 'form-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ProductModel::class)->title('name')->canonical(false)->allowAll()->borderless();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name')->required()->default('New product'),
            Field::make('SKU', 'sku')->resolveUsing(function (mixed $value) {
                FormClosureLog::$calls++;

                return $value;
            }),
            Field::make('Price', 'price_cents')->default(function () {
                FormClosureLog::$calls++;

                return 500;
            }),
            Section::make('Audit', [Field::make('Created', 'created_at')->onlyOnDetail()]),
        ];
    }

    public static function defaultAttributes(LatheRequest $request): array
    {
        return ['sku' => 'SKU-', 'price_cents' => 100];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(Product::class);
    FormClosureLog::$calls = 0;
    // A fixed stored time, so the token below is a literal.
    DB::table('orders')->where('id', 12)->update(['updated_at' => '2026-03-04 05:06:07']);
});

it('page_shells_check_the_ability', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12/edit')
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Error', false)->where('status', 403)->where('shell', true));
    $this->get('/lathe/resources/orders/99999999/edit')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Error', false)->where('status', 404)->where('shell', true));
    // The order policy has no create method, so nobody creates an order.
    $this->get('/lathe/resources/orders/new')
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Error', false)->where('status', 403)->where('shell', true));

    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12/edit')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Resources/Form', false));
    $this->get('/lathe/resources/form-products/new')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Resources/Form', false));
})->group('regression');

it('returns the creation fields with defaults, layout and borderless', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $json = LatheTest::resource('form-products')->creationFields()->assertOk()->json();

    expect(array_keys($json))->toBe(['fields', 'defaults', 'layout', 'borderless'])
        ->and(array_column($json['fields'], 'key'))->toBe(['name', 'sku', 'price_cents'])
        ->and(array_column($json['fields'], 'value'))->toBe(['New product', 'SKU-', 500])
        ->and($json['fields'][0]['required'])->toBeTrue()
        ->and($json['defaults'])->toBe(['sku' => 'SKU-', 'price_cents' => 500, 'name' => 'New product'])
        ->and(array_column($json['layout'], 'name'))->toBe(['Details'])
        ->and($json['layout'][0]['fields'])->toBe(['name', 'sku', 'price_cents'])
        ->and($json['borderless'])->toBeTrue();
});

it('refuses the creation fields without the create ability', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    LatheTest::resource('orders')->creationFields()->assertForbidden();
});

it('returns the update fields with their values and the traffic-cop token', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $json = LatheTest::resource('orders')->updateFields(12)->assertOk()->json();

    expect(array_keys($json))->toBe(['fields', 'layout', 'updatedAt'])
        ->and(array_column($json['fields'], 'key'))->toBe(['number', 'status', 'placed_at', 'total_cents', 'items'])
        ->and($json['fields'][0]['value'])->toBe('ORD-00099454')
        ->and($json['layout'][0]['fields'])->toBe(['number', 'status', 'placed_at'])
        ->and($json['layout'][1]['type'])->toBe('tab-group')
        ->and($json['updatedAt'])->toBe('2026-03-04T05:06:07.000Z');
});

it('refuses the update fields to the editor and answers 404 for a missing order', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    LatheTest::resource('orders')->updateFields(12)->assertForbidden();
    LatheTest::resource('orders')->updateFields(99999999)->assertNotFound();
});

it('resolves only the fields ?only= names and runs no other field closure', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $product = ProductModel::query()->orderBy('id')->firstOrFail();

    $created = LatheTest::resource('form-products')->creationFields(['only' => 'name'])->assertOk()->json();
    $updated = $this->getJson("/lathe/api/resources/form-products/{$product->id}/update-fields?only=name")->assertOk()->json();

    expect(array_column($created['fields'], 'key'))->toBe(['name'])
        ->and($created['defaults'])->toBe(['sku' => 'SKU-', 'price_cents' => 100, 'name' => 'New product'])
        ->and(array_column($updated['fields'], 'key'))->toBe(['name'])
        ->and($updated['fields'][0]['value'])->toBe($product->name)
        ->and(FormClosureLog::$calls)->toBe(0);
});

it('renders the create page with the creation fields and the create label', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    $form = LatheTest::resource('form-products')->creationFields()->json();

    $this->get('/lathe/resources/form-products/new')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Form', false)
            ->where('resource.uriKey', 'form-products')
            ->where('mode', 'create')
            ->where('id', null)
            ->where('title', 'Create Product')
            ->where('submitLabel', 'Create Product')
            ->where('form', $form)
            ->where('breadcrumbs', [
                ['label' => 'Dashboard', 'href' => '/lathe'],
                ['label' => 'Products', 'href' => '/lathe/resources/form-products'],
                ['label' => 'Create Product', 'href' => null],
            ]));
});

it('renders the edit page with the update fields and the update label', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $this->get('/lathe/resources/orders/12/edit')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Form', false)
            ->where('resource.uriKey', 'orders')
            ->where('mode', 'update')
            ->where('id', 12)
            ->where('title', 'ORD-00099454')
            ->where('submitLabel', 'Update Order')
            ->where('form.updatedAt', '2026-03-04T05:06:07.000Z')
            ->where('form.fields.0.value', 'ORD-00099454')
            ->where('breadcrumbs', [
                ['label' => 'Dashboard', 'href' => '/lathe'],
                ['label' => 'Orders', 'href' => '/lathe/resources/orders'],
                ['label' => '#12', 'href' => '/lathe/resources/orders/12'],
                ['label' => 'Edit', 'href' => null],
            ]));
});
