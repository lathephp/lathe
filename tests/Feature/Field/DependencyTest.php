<?php

namespace Lathe\Tests\Feature\Field;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lathe\Field\Field;
use Lathe\Field\FieldPatch;
use Lathe\Field\FormData;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use LogicException;
use RuntimeException;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class DependentCustomers extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        $chain = [];

        foreach (range(1, 7) as $step) {
            $chain[] = Field::text("Step {$step}", "step_{$step}")->dependsOn(['step_'.($step - 1)], fn (FieldPatch $field, FormData $formData) => $field->setValue($formData->get('step_'.($step - 1)).'+'));
        }

        return [
            Field::text('Name'),
            Field::select('Country')->options(['US', 'BD']),
            Field::select('State')->optionsFrom('country', ['US' => ['NY', 'CA'], 'BD' => ['Dhaka']]),
            Field::select('City')->optionsFrom('country', fn ($value) => $value === 'BD' ? ['Dhaka', 'Khulna'] : ['Boston']),
            Field::text('Company name')->dependsOn(['type'], function (FieldPatch $field, LatheRequest $request, FormData $formData) {
                $formData->get('type') === 'company' ? $field->show()->required() : $field->hide()->readonly();
            }),
            Field::text('Ping', 'ping')->dependsOn('pong', fn (FieldPatch $field) => $field->setValue('ping')),
            Field::text('Pong', 'pong')->dependsOn('ping', fn (FieldPatch $field) => $field->setValue('pong')),
            Field::text('Creating')->dependsOnCreating('name', fn (FieldPatch $field) => $field->setValue('on create')),
            Field::text('Updating')->dependsOnUpdating('name', fn (FieldPatch $field) => $field->setValue('on update')),
            Field::text('Boom')->dependsOn('name', fn () => throw new RuntimeException('boom')),
            Field::text('Many')->dependsOn('name', fn (FieldPatch $field) => $field->options(range(1, 101))),
            Field::text('Data')->dependsOn('name', fn (FieldPatch $field, FormData $formData) => $field->setValue([
                'get' => $formData->get('name'), 'has' => $formData->has('missing'), 'boolean' => $formData->boolean('flag'),
                'all' => array_keys($formData->all()), 'customer' => $formData->resource('customers', 1)?->getKey(), 'request' => $formData->request() instanceof LatheRequest,
            ])),
            ...$chain,
        ];
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('id', '!=', 2);
    }
}

class DependentOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Customer'),
            Field::text('Picked')->dependsOn('customer', fn (FieldPatch $field, FormData $formData) => $field->setValue($formData->get('customer'))),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(DependentCustomers::class, DependentOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function lookup(array $body): TestResponse
{
    return test()->postJson('/lathe/api/resources/dependent-customers/field-dependencies', ['context' => 'create', 'values' => [], 'dirty' => [], ...$body]);
}

it('patches a field from the values: show, hide, required, readonly', function () {
    expect(lookup(['fields' => ['company_name'], 'values' => ['type' => 'company']])->assertOk()->json('patches.company_name'))->toBe(['visible' => true, 'required' => true])
        ->and(lookup(['fields' => ['company_name'], 'values' => ['type' => 'person']])->json('patches.company_name'))->toBe(['visible' => false, 'readonly' => true]);
});

it('looks options up on the server and sends an array source to the browser', function () {
    expect(lookup(['fields' => ['city'], 'values' => ['country' => 'BD']])->json('patches.city.options'))->toBe([['value' => 'Dhaka', 'label' => 'Dhaka'], ['value' => 'Khulna', 'label' => 'Khulna']])
        ->and(Field::select('State')->optionsFrom('country', ['BD' => ['Dhaka']])->jsonSerialize()['optionsFrom'])->toBe(['field' => 'country', 'map' => ['BD' => [['value' => 'Dhaka', 'label' => 'Dhaka']]]]);
});

it('refuses 21 keys and more than 100 options', function () {
    lookup(['fields' => array_map(fn (int $i) => "f{$i}", range(1, 21))])->assertUnprocessable()->assertJsonValidationErrors(['fields']);

    expect(fn () => (new FieldPatch(Field::text('Many')))->options(range(1, 101)))->toThrow(LogicException::class, 'Many');
});

it('never overwrites a dirty value and reports the changed ones', function () {
    $clean = lookup(['fields' => ['step_1'], 'values' => ['step_0' => 'a']])->json();
    $dirty = lookup(['fields' => ['step_1'], 'values' => ['step_0' => 'a'], 'dirty' => ['step_1']])->json();

    expect($clean['changed']['step_1'])->toBe('a+')
        ->and($dirty['changed'])->toBe([])
        ->and($dirty['patches']['step_1'])->toBe([]);
});

it('cascades at most 5 rounds deep and stops a cycle at its first repeat', function () {
    $chain = lookup(['fields' => ['step_1'], 'values' => ['step_0' => 'a']])->json('changed');
    $cycle = lookup(['fields' => ['ping'], 'values' => []])->json('changed');

    expect(array_keys($chain))->toBe(['step_1', 'step_2', 'step_3', 'step_4', 'step_5'])
        ->and($chain['step_5'])->toBe('a+++++')
        ->and($cycle)->toBe(['ping' => 'ping', 'pong' => 'pong']);
});

it('runs dependsOnCreating and dependsOnUpdating in their own context only', function () {
    $create = lookup(['fields' => ['creating', 'updating']])->json('changed');
    $update = lookup(['fields' => ['creating', 'updating'], 'context' => 'update', 'id' => 1])->assertOk()->json('changed');

    expect($create)->toBe(['creating' => 'on create'])->and($update)->toBe(['updating' => 'on update']);
});

it('finds the record through the scoped query', function () {
    lookup(['fields' => ['name'], 'context' => 'update', 'id' => 2])->assertNotFound();
});

it('answers a throwing callback with an error the browser keeps its state on', function () {
    lookup(['fields' => ['boom'], 'values' => ['name' => 'x']])->assertServerError();
});

it('gives the callback the form values through FormData', function () {
    $data = lookup(['fields' => ['data'], 'values' => ['name' => 'Ann', 'flag' => 'true']])->json('changed.data');

    expect($data)->toBe(['get' => 'Ann', 'has' => false, 'boolean' => true, 'all' => ['name', 'flag'], 'customer' => 1, 'request' => true]);
});

it("gives the callback a relation field's key, not the picker's value and display", function () {
    $picked = $this->postJson('/lathe/api/resources/dependent-orders/field-dependencies', [
        'context' => 'create', 'dirty' => [], 'fields' => ['picked'], 'values' => ['customer' => ['value' => 7, 'display' => 'Ann']],
    ])->assertOk()->json('changed.picked');

    expect($picked)->toBe(7);
});
