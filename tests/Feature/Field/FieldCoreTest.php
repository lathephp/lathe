<?php

namespace Lathe\Tests\Feature\Field;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Evaluator;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\FieldResolver;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use LogicException;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Counts how often each field closure runs. */
class FieldCoreLog
{
    /** @var array<string, int> */
    public static array $calls = [];

    /** @var list<Field> */
    public static array $kept = [];

    public static function hit(string $name): void
    {
        self::$calls[$name] = (self::$calls[$name] ?? 0) + 1;
    }
}

class FieldCoreOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return FieldCoreLog::$kept = [
            Field::make('Number')->sortable(),
            Field::make('Status'),
            Field::make('Secret', 'notes')->canSee(function (LatheRequest $request) {
                FieldCoreLog::hit('canSee');

                return $request->user()?->getAttribute('email') === 'admin@lathephp.com';
            }),
            Field::make('Shout', fn (Order $order) => strtoupper($order->status)),
        ];
    }

    public function fieldsForCreate(LatheRequest $request): array
    {
        return [Field::make('Number')];
    }

    public function fieldsForUpdate(LatheRequest $request): array
    {
        return [];
    }
}

function fieldCoreRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/field-core-orders'));
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(FieldCoreOrder::class);
    FieldCoreLog::$calls = [];
});

it('throws when a modifier runs on a field after fields() returned', function () {
    (new FieldCoreOrder)->fieldsFor(FieldContext::Index, fieldCoreRequest());

    expect(fn () => FieldCoreLog::$kept[0]->sortable(false))->toThrow(LogicException::class, 'frozen')
        ->and(fn () => FieldCoreLog::$kept[1]->help('late'))->toThrow(LogicException::class);
});

it('resolves only the named fields and runs no other closure', function () {
    $fields = (new FieldCoreOrder)->fieldsFor(FieldContext::Index, fieldCoreRequest());

    $cells = FieldResolver::cells(Order::findOrFail(12), $fields->onlyKeys(['number']));

    expect(array_keys($cells))->toBe(['number'])
        ->and(FieldCoreLog::$calls)->toBe([]);
});

it('lets a context list replace fields() and ignores an empty one', function () {
    $resource = new FieldCoreOrder;
    $request = fieldCoreRequest();

    expect($resource->fieldsFor(FieldContext::Create, $request)->map->label()->all())->toBe(['Number'])
        ->and($resource->fieldsFor(FieldContext::Update, $request)->map->label()->all())->toBe(['Number', 'Status', 'Secret', 'Shout'])
        ->and($resource->fieldsFor(FieldContext::Preview, $request)->count())->toBe(4)
        ->and($resource->fieldsFor(FieldContext::Peeking, $request)->count())->toBe(4);
});

it('shows a field in the contexts its visibility names', function (Field $field, array $shown) {
    $contexts = array_values(array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context, fieldCoreRequest())));

    expect(array_map(fn (FieldContext $context) => $context->value, $contexts))->toBe($shown);
})->with([
    'default' => [fn () => Field::make('A'), ['index', 'detail', 'create', 'update']],
    'onlyOnIndex' => [fn () => Field::make('A')->onlyOnIndex(), ['index']],
    'onlyOnDetail' => [fn () => Field::make('A')->onlyOnDetail(), ['detail']],
    'onlyOnForms' => [fn () => Field::make('A')->onlyOnForms(), ['create', 'update']],
    'exceptOnForms' => [fn () => Field::make('A')->exceptOnForms(), ['index', 'detail']],
    'onlyOnPreview' => [fn () => Field::make('A')->onlyOnPreview(), ['preview']],
    'showWhenPeeking' => [fn () => Field::make('A')->showWhenPeeking(), ['index', 'detail', 'create', 'update', 'peeking']],
    'hideFromIndex closure true' => [fn () => Field::make('A')->hideFromIndex(fn () => true), ['detail', 'create', 'update']],
    'hideFromIndex closure false' => [fn () => Field::make('A')->hideFromIndex(fn () => false), ['index', 'detail', 'create', 'update']],
    'showOnDetail false' => [fn () => Field::make('A')->showOnDetail(false), ['index', 'create', 'update']],
    'hideWhenCreating' => [fn () => Field::make('A')->hideWhenCreating(), ['index', 'detail', 'update']],
    'showOnPreview' => [fn () => Field::make('A')->showOnPreview(), ['index', 'detail', 'create', 'update', 'preview']],
]);

it('gives index visibility closures no record and other contexts the record', function () {
    $seen = [];
    $field = Field::make('A')
        ->showOnIndex(function (mixed $record) use (&$seen) {
            $seen['index'] = $record;

            return true;
        })
        ->showOnDetail(function (mixed $record) use (&$seen) {
            $seen['detail'] = $record;

            return true;
        });
    $order = Order::findOrFail(12);

    $field->isShownOn(FieldContext::Index, fieldCoreRequest(), $order);
    $field->isShownOn(FieldContext::Detail, fieldCoreRequest(), $order);

    expect($seen['index'])->toBeNull()
        ->and($seen['detail'])->toBe($order);
});

it('runs canSee once per request for a 100-row index and hides the column from a refused user', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $admin = $this->getJson('/lathe/api/resources/field-core-orders?per_page=100')->assertOk();

    expect($admin->json('table.rows'))->toHaveCount(100)
        ->and(array_column($admin->json('table.columns'), 'key'))->toContain('notes')
        ->and(FieldCoreLog::$calls['canSee'])->toBe(1);

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());
    $editor = $this->getJson('/lathe/api/resources/field-core-orders?per_page=100')->assertOk();

    expect(array_column($editor->json('table.columns'), 'key'))->not->toContain('notes')
        ->and($editor->json('table.rows.0.cells'))->not->toHaveKey('notes');
});

it('keeps a computed field off forms and off sort, and refuses filterable() on it', function () {
    $field = Field::make('Shout', fn (Order $order) => strtoupper($order->status))->sortable();

    expect($field->isComputed())->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Update))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Detail))->toBeTrue()
        ->and($field->isSortable())->toBeFalse()
        ->and($field->jsonSerialize()['sortable'])->toBeFalse()
        ->and($field->resolve(Order::findOrFail(12)))->toBe(strtoupper(Order::findOrFail(12)->status))
        ->and(fn () => Field::make('Shout', fn () => 'x')->filterable())->toThrow(LogicException::class, 'Shout');
});

it('filters a collection by context, canSee, computed, sortable and attribute', function () {
    $request = fieldCoreRequest();
    $request->setUserResolver(fn () => null);
    $fields = FieldCollection::make([
        Field::make('Name')->sortable(),
        Field::make('SKU', 'sku'),
        Field::make('Weight')->onlyOnDetail(),
        Field::make('Cost')->canSee(fn () => false),
        Field::make('Label', fn () => 'x'),
    ]);

    expect($fields->forContext(FieldContext::Create, $request)->map->label()->all())->toBe(['Name', 'SKU', 'Cost'])
        ->and($fields->authorized($request)->map->label()->all())->toBe(['Name', 'SKU', 'Weight', 'Label'])
        ->and($fields->withoutComputed()->map->label()->all())->toBe(['Name', 'SKU', 'Weight', 'Cost'])
        ->and($fields->sortable()->map->label()->all())->toBe(['Name'])
        ->and($fields->findByAttribute('sku')?->label())->toBe('SKU')
        ->and($fields->findByAttribute('nope'))->toBeNull();
});

it('sends every presentation modifier in the payload', function () {
    $payload = Field::make('Total')
        ->nullable()->stacked()->fullWidth()->columnSpan(2)->indexWidth(120)->borderless()->compact()
        ->textAlign('right')->placeholder('0.00')->help('In dollars')->helpWidth(200)->withSection('billing')
        ->wrapping()->withMeta(['unit' => 'USD'])
        ->jsonSerialize();

    expect($payload)->toMatchArray([
        'nullable' => true, 'stacked' => true, 'fullWidth' => true, 'columnSpan' => 2, 'indexWidth' => 120,
        'borderless' => true, 'compact' => true, 'textAlign' => 'right', 'placeholder' => '0.00',
        'help' => 'In dollars', 'helpWidth' => 200, 'section' => 'billing', 'wrapping' => true,
    ])->and((array) $payload['meta'])->toBe(['unit' => 'USD'])
        ->and(Field::make('A')->wrapping(false)->jsonSerialize()['wrapping'])->toBeFalse();
});

it('takes macro, when and tap on any field', function () {
    Field::macro('money', fn () => $this->textAlign('right'));

    $field = Field::make('Total')->money()->when(true, fn (Field $field) => $field->compact())->tap(fn (Field $field) => $field->stacked());

    expect($field->jsonSerialize())->toMatchArray(['textAlign' => 'right', 'compact' => true, 'stacked' => true]);
});

it('closure_evaluator_fills_defaults_and_positional_args', function () {
    $evaluator = new Evaluator;
    $order = new Order(['status' => 'paid']);
    $request = fieldCoreRequest();
    $field = Field::make('Status');
    $values = ['record' => $order, 'value' => 'paid', 'attribute' => 'status', 'request' => $request, 'field' => $field, 'context' => FieldContext::Detail];

    expect($evaluator->call(fn ($state, $column, $operation) => [$state, $column, $operation], $values))->toBe(['paid', 'status', FieldContext::Detail])
        ->and($evaluator->call(fn (Order $o, LatheRequest $r, Field $f) => [$o, $r, $f], $values))->toBe([$order, $request, $field])
        ->and($evaluator->call(fn (FieldCoreLog $log) => $log, $values))->toBeInstanceOf(FieldCoreLog::class)
        ->and($evaluator->call(fn ($first, $second) => [$first, $second], $values, ['one', 'two']))->toBe(['one', 'two'])
        ->and($evaluator->call(fn ($model) => $model, $values))->toBe($order)
        ->and($evaluator->call(fn ($value, $suffix = '!') => $value.$suffix, $values))->toBe('paid!')
        ->and($evaluator->call(fn (?Model $record = null) => $record, ['record' => null]))->toBeNull();
})->group('regression');

it('closure_reflection_is_cached_per_closure', function () {
    $request = fieldCoreRequest();
    app()->instance(LatheRequest::class, $request);
    $field = Field::make('Shout', fn (Order $order) => strtoupper($order->status));

    foreach (Order::query()->limit(100)->get() as $order) {
        FieldResolver::cells($order, [$field]);
    }

    expect(Evaluator::for($request)->reflections)->toBe(1);
})->group('regression');
