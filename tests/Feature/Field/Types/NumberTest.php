<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\CurrencyField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\NumberField;
use Lathe\Field\TokenField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class NumberThing extends Model
{
    protected $table = 'number_things';

    protected $guarded = [];

    protected $casts = ['price' => 'decimal:2'];
}

class NumberThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(NumberThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'number-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::number('Reorder level', 'reorder_level')->min(0)->max(10)->step(1)->nullable(),
            Field::currency('Price', 'price')->max(1000)->nullable(),
            Field::currency('Total', 'total_cents')->asMinorUnits()->nullable(),
            Field::currency('Yen', 'yen')->currencyCode('JPY')->nullable(),
            Field::token('Api token', 'api_token')->mask(4)->nullable(),
        ];
    }
}

class LockedThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(NumberThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'locked-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name')->immutable(),
            // Field::boolean() is another task's type, so a plain field stands in for the boolean column.
            Field::make('Active', 'active')->immutable(),
            Field::currency('Locked total', 'total_cents')->asMinorUnits()->immutable(),
        ];
    }
}

function numberRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/number-things'));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function numberPayload(array $overrides = []): array
{
    return ['name' => 'Widget', 'reorder_level' => 5, 'price' => '9.99', 'total_cents' => '1.00', 'yen' => '100', 'api_token' => null, ...$overrides];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('number_things');
    Schema::create('number_things', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('reorder_level')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->bigInteger('total_cents')->nullable();
        $table->integer('yen')->nullable();
        $table->string('api_token')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(NumberThingResource::class, LockedThingResource::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('builds the number, currency and token fields from their factories', function () {
    $number = Field::number('Qty');
    $computed = Field::number('Double', fn (Model $record) => 2);

    expect($number)->toBeInstanceOf(NumberField::class)
        ->and($number->getComponent())->toBe('number-field')
        ->and($number->jsonSerialize()['textAlign'])->toBe('right')
        ->and($computed->jsonSerialize()['textAlign'])->toBe('center')
        ->and($computed->isShownOn(FieldContext::Create))->toBeFalse()
        ->and(Field::currency('Price'))->toBeInstanceOf(CurrencyField::class)
        ->and(Field::currency('Price')->getComponent())->toBe('currency-field')
        ->and(Field::currency('Price')->jsonSerialize()['textAlign'])->toBe('right')
        ->and(Field::token('Key'))->toBeInstanceOf(TokenField::class)
        ->and(Field::token('Key')->getComponent())->toBe('token-field');
});

it('sends min, max and step as input attributes', function () {
    $schema = Field::number('Level', 'reorder_level')->min(0)->max(10)->step(1)->schema(FieldContext::Update, numberRequest());

    expect($schema['min'])->toEqual(0)->and($schema['max'])->toEqual(10)->and($schema['step'])->toEqual(1);
});

it('number_and_currency_bounds_are_enforced_on_server', function () {
    $tester = LatheTest::resource('number-things');

    $tester->store(numberPayload(['reorder_level' => 11]))->assertUnprocessable()->assertJsonValidationErrors('reorder_level');
    $tester->store(numberPayload(['reorder_level' => -1]))->assertUnprocessable()->assertJsonValidationErrors('reorder_level');
    $tester->store(numberPayload(['reorder_level' => 'many']))->assertUnprocessable()->assertJsonValidationErrors('reorder_level');
    $tester->store(numberPayload(['price' => '1000.01']))->assertUnprocessable()->assertJsonValidationErrors('price');
    $tester->store(numberPayload(['price' => '1e3']))->assertUnprocessable()->assertJsonValidationErrors('price');

    $id = $tester->store(numberPayload(['reorder_level' => 10, 'price' => '1000']))->assertCreated()->json('id');
    $tester->update($id, numberPayload(['reorder_level' => 11]))->assertUnprocessable()->assertJsonValidationErrors('reorder_level');
    $tester->update($id, numberPayload(['reorder_level' => 10]))->assertOk();
})->group('regression');

it('travels minor units as a decimal string and parses it back with integer rounding', function () {
    $tester = LatheTest::resource('number-things');
    $field = Field::currency('Total', 'total_cents')->asMinorUnits();

    expect($field->resolve(new NumberThing(['total_cents' => 123450])))->toBe('1234.50')
        ->and($field->resolve(new NumberThing(['total_cents' => 5])))->toBe('0.05')
        ->and($field->resolve(new NumberThing(['total_cents' => -250])))->toBe('-2.50')
        ->and($field->resolve(new NumberThing(['total_cents' => null])))->toBeNull();

    $id = $tester->store(numberPayload(['total_cents' => '12.35']))->assertCreated()->json('id');
    expect(DB::table('number_things')->where('id', $id)->value('total_cents'))->toBe(1235);

    $tester->update($id, numberPayload(['total_cents' => '12.345']))->assertOk();
    expect(DB::table('number_things')->where('id', $id)->value('total_cents'))->toBe(1235);

    $tester->update($id, numberPayload(['total_cents' => '12.995']))->assertOk();
    expect(DB::table('number_things')->where('id', $id)->value('total_cents'))->toBe(1300);

    $tester->update($id, numberPayload(['total_cents' => 7]))->assertOk();
    expect(DB::table('number_things')->where('id', $id)->value('total_cents'))->toBe(700);

    $tester->update($id, numberPayload(['total_cents' => null]))->assertOk();
    expect(DB::table('number_things')->where('id', $id)->value('total_cents'))->toBeNull();
});

it('takes its step and wire digits from the currency code', function () {
    $usd = Field::currency('Price', 'price');
    $yen = Field::currency('Yen', 'yen')->currencyCode('JPY');
    $request = numberRequest();

    expect($usd->schema(FieldContext::Update, $request)['step'])->toBe('0.01')
        ->and($yen->schema(FieldContext::Update, $request)['step'])->toBe('1')
        ->and($yen->schema(FieldContext::Update, $request)['currency'])->toBe('JPY')
        ->and($yen->resolve(new NumberThing(['yen' => 1234])))->toBe('1234')
        ->and($usd->resolve(new NumberThing(['price' => '12.5'])))->toBe('12.50')
        ->and($usd->resolve(new NumberThing(['price' => 12.5])))->toBe('12.50');
});

it('shows currency in the list through the formatter and right-aligned', function () {
    $tester = LatheTest::resource('number-things');
    $tester->store(numberPayload(['price' => 12.5, 'total_cents' => '1234.50']))->assertCreated();

    $json = $tester->index()->assertOk()->json();
    $cells = $json['table']['rows'][0]['cells'];
    $columns = collect($json['table']['columns'])->keyBy('key');

    expect($cells['price']['value'])->toBe('12.50')
        ->and($cells['price']['display'])->toBe('$12.50')
        ->and($cells['total_cents']['value'])->toBe('1234.50')
        ->and($cells['total_cents']['display'])->toBe('$1,234.50')
        ->and($columns['total_cents']['textAlign'])->toBe('right')
        ->and($columns['total_cents']['component'])->toBe('currency-field');
});

it('formats money with the symbol override', function () {
    expect(Field::currency('Price')->formatMoney('1234.5'))->toBe('$1,234.50')
        ->and(Field::currency('Price')->symbol('US$')->formatMoney('12.5'))->toBe('US$12.50')
        ->and(Field::currency('Price')->currencyCode('JPY')->formatMoney('1234'))->toBe('¥1,234')
        ->and(Field::currency('Price')->formatMoney(null))->toBeNull()
        ->and(Field::currency('Price')->formatMoney(''))->toBeNull();
});

it('carries no float in a currency payload', function () {
    $field = Field::currency('Total', 'total_cents')->asMinorUnits()->min(0)->max(2000);
    $model = new NumberThing(['total_cents' => 123450]);
    $floats = fn (array $payload) => array_filter(
        (array) json_decode((string) json_encode($payload), true),
        fn (mixed $value) => is_float($value),
    );

    expect($floats($field->schema(FieldContext::Update, numberRequest(), $model)))->toBe([])
        ->and($floats($field->cell($model)))->toBe([])
        ->and($field->schema(FieldContext::Update, numberRequest(), $model)['max'])->toBe('2000');
});

it('masks a token on the index and keeps the raw value for the form', function () {
    $tester = LatheTest::resource('number-things');
    $tester->store(numberPayload(['api_token' => 'abcd1234efgh']))->assertCreated();

    $index = $tester->index()->assertOk();
    $cell = $index->json('table.rows.0.cells.api_token');
    $model = NumberThing::firstOrFail();
    $field = Field::token('Api token', 'api_token')->mask(4);

    expect($cell)->toBe(['value' => 'abcd********', 'display' => 'abcd********'])
        ->and($index->getContent())->not->toContain('1234efgh')
        ->and($field->cell($model)['value'])->toBe('abcd********')
        ->and($field->resolve($model))->toBe('abcd1234efgh')
        ->and(Field::token('Api token', 'api_token')->mask(2, 3, '#')->cell($model)['display'])->toBe('ab###234efgh')
        ->and(Field::token('Api token', 'api_token')->cell($model)['display'])->toBe('************');
});

it('immutable_boolean_and_currency_survive_update', function () {
    $id = DB::table('number_things')->insertGetId(['name' => 'Original', 'active' => true, 'total_cents' => 123450]);

    LatheTest::resource('locked-things')->update($id, ['name' => 'Changed', 'active' => false, 'total_cents' => '1.00'])->assertOk();

    $row = DB::table('number_things')->where('id', $id)->first();
    expect($row->name)->toBe('Original')
        ->and($row->active)->toBe(1)
        ->and($row->total_cents)->toBe(123450);
})->group('regression');
