<?php

namespace Lathe\Tests\Feature\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\DefaultLifecycle;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\FieldLifecycle;
use Lathe\Field\IdField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Models\UlidThing;
use Lathe\Tests\Fixtures\Models\UuidThing;
use Workbench\App\Models\Order;
use Workbench\Database\Seeders\DemoSeeder;

/** Records the order the four lifecycle phases run in. */
class SpyLifecycle implements FieldLifecycle
{
    /** @var list<string> */
    public array $calls = [];

    public function extract(object $record, string $attribute): mixed
    {
        $this->calls[] = 'extract';

        return 'raw';
    }

    public function present(mixed $raw, object $record, string $attribute): mixed
    {
        $this->calls[] = 'present';

        return "wire:{$raw}";
    }

    public function parse(LatheRequest $request, string $attribute): mixed
    {
        $this->calls[] = 'parse';

        return 'parsed';
    }

    public function persist(object $model, string $attribute, mixed $value): ?Closure
    {
        $this->calls[] = 'persist';

        return null;
    }
}

/**
 * @param  array<string, mixed>  $input
 */
function lifecycleRequest(array $input = []): LatheRequest
{
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/orders', 'PUT', $input));
    app()->instance(LatheRequest::class, $request);

    return $request;
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

it('runs a custom lifecycle through extract, present, parse and persist', function () {
    $spy = new SpyLifecycle;
    $field = Field::make('Status')->useLifecycle($spy);

    $wire = $field->resolve(new Order);
    $field->fill(lifecycleRequest(['status' => 'x']), new Order);

    expect($wire)->toBe('wire:raw')
        ->and($spy->calls)->toBe(['extract', 'present', 'parse', 'persist'])
        ->and($field->lifecycle())->toBe($spy)
        ->and(Field::make('A')->lifecycle())->toBeInstanceOf(DefaultLifecycle::class);
});

it('reads and writes Eloquent attributes through their casts', function () {
    $order = Order::findOrFail(12);
    $placed = Field::make('Placed', 'placed_at');

    $placed->fill(lifecycleRequest(['placed_at' => '2026-10-14 09:00:00']), $order);

    expect($order->placed_at)->toBeInstanceOf(Carbon::class)
        ->and($placed->resolve($order))->toBe('2026-10-14T09:00:00Z');
});

it('lets withContext set the context a closure reads while the field resolves', function () {
    $field = Field::make('Status')->resolveUsing(fn (?FieldContext $context) => $context?->name);

    expect($field->withContext(FieldContext::Update)->resolve(new Order))->toBe('Update')
        ->and($field->resolve(new Order))->toBeNull();
});

it('fill_using_is_honoured', function () {
    $order = Order::findOrFail(12);
    $after = fn () => null;
    $field = Field::make('Status')->fillUsing(function (LatheRequest $request, Model $model, string $attribute, string $requestAttribute) use ($after) {
        $model->setAttribute($attribute, strtoupper((string) $request->input($requestAttribute)));

        return $after;
    });

    $returned = $field->fill(lifecycleRequest(['status' => 'shipped']), $order);

    expect($order->status)->toBe('SHIPPED')
        ->and($returned)->toBe($after);
})->group('regression');

it('lets resolveUsing replace extract and present, and displayUsing change only what is shown', function () {
    $order = Order::findOrFail(12);
    $resolved = Field::make('Status')->resolveUsing(fn ($record, $attribute) => "{$attribute}:{$record->status}");
    $displayed = Field::make('Status')->displayUsing(fn ($value) => strtoupper((string) $value));

    expect($resolved->resolve($order))->toBe("status:{$order->status}")
        ->and($displayed->resolve($order))->toBe($order->status)
        ->and($displayed->resolveForDisplay($order))->toBe(strtoupper($order->status))
        ->and($displayed->cell($order)['display'])->toBe(strtoupper($order->status));
});

it('skips an immutable field on an existing model and fills it on a new one', function () {
    $order = Order::findOrFail(12);
    $field = Field::make('Status')->immutable();

    $field->fill(lifecycleRequest(['status' => 'changed']), $order);
    $fresh = new Order;
    $field->fill(lifecycleRequest(['status' => 'changed']), $fresh);

    expect($order->status)->not->toBe('changed')
        ->and($fresh->status)->toBe('changed')
        ->and($field->schema(FieldContext::Update, lifecycleRequest(), $order))->toMatchArray(['readonly' => true, 'immutable' => true])
        ->and($field->schema(FieldContext::Create, lifecycleRequest()))->toMatchArray(['readonly' => false, 'immutable' => false]);
});

it('saves null for the null values of a nullable field', function (array|Closure $nullValues, mixed $input, mixed $stored) {
    $order = new Order;
    Field::make('Status')->nullable()->nullValues($nullValues)->fill(lifecycleRequest(['status' => $input]), $order);

    expect($order->getAttributes()['status'])->toBe($stored);
})->with([
    'int 0 is null' => [[0], 0, null],
    'string 0 stays' => [[0], '0', '0'],
    'closure' => [fn () => fn ($value) => $value === 'none', 'none', null],
]);

it('saves null for an empty input by default, but only on a nullable field', function () {
    $nullable = new Order;
    $plain = new Order;
    Field::make('Status')->nullable()->fill(lifecycleRequest(['status' => '']), $nullable);
    Field::make('Status')->fill(lifecycleRequest(['status' => '']), $plain);

    expect($nullable->getAttributes()['status'])->toBeNull()
        ->and($plain->getAttributes()['status'])->toBe('');
});

it('gives the create form a default, a Closure default the request', function () {
    $request = lifecycleRequest(['country' => 'BD']);

    expect(Field::make('Country')->default('US')->resolveDefaultValue($request))->toBe('US')
        ->and(Field::make('Country')->default(fn (LatheRequest $request) => $request->input('country'))->resolveDefaultValue($request))->toBe('BD')
        ->and(Field::make('Country')->resolveDefaultValue($request))->toBeNull();
});

it('decides readonly, required and immutable from a Closure with the record', function () {
    $order = Order::findOrFail(12);
    $field = Field::make('Status')->readonly(fn ($record) => $record?->getKey() === 12)->required(fn () => true);

    expect($field->isReadonly(lifecycleRequest(), $order))->toBeTrue()
        ->and($field->isReadonly(lifecycleRequest(), new Order))->toBeFalse()
        ->and($field->isRequired())->toBeTrue()
        ->and(FieldCollection::make([$field, Field::make('Number')])->withoutReadonly(lifecycleRequest(), $order)->map->label()->all())->toBe(['Number']);
});

it('sends dates as UTC ISO 8601 and integers past 2^53 - 1 as strings', function () {
    expect(DefaultLifecycle::wire(Carbon::parse('2026-10-14 09:00:00', 'Pacific/Kiritimati')))->toBe('2026-10-13T19:00:00Z')
        ->and(DefaultLifecycle::wire(9007199254740991))->toBe(9007199254740991)
        ->and(DefaultLifecycle::wire(9007199254740993))->toBe('9007199254740993');
});

it('sends the key 9007199254740993 as a string through IdField::forModel', function () {
    $order = Order::findOrFail(12);
    $order->id = 9007199254740993;

    $field = IdField::forModel($order);

    expect($field->resolve($order))->toBe('9007199254740993')
        ->and(IdField::forModel(Order::findOrFail(12))->resolve(Order::findOrFail(12)))->toBe(12)
        ->and($field->isShownOn(FieldContext::Index))->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeFalse()
        ->and(array_filter(FieldContext::cases(), fn (FieldContext $context) => IdField::hidden()->isShownOn($context)))->toBe([]);
});

it('round-trips UUID and ULID keys as strings', function (string $model, string $type) {
    Schema::create((new $model)->getTable(), function (Blueprint $table) use ($type) {
        $table->{$type}('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
    /** @var Model $thing */
    $thing = $model::query()->create(['name' => 'Thing']);

    $wire = IdField::forModel($thing)->resolve($thing);

    expect($wire)->toBeString()->toBe($thing->getKey())
        ->and($model::query()->findOrFail($wire)->getKey())->toBe($wire);
})->with([[UuidThing::class, 'uuid'], [UlidThing::class, 'ulid']]);
