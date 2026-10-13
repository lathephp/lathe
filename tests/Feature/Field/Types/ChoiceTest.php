<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\BooleanField;
use Lathe\Field\BooleanGroupField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\MultiSelectField;
use Lathe\Field\OptionGroup;
use Lathe\Field\SelectField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use LogicException;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

enum ChoiceLevel: string
{
    case Low = 'low';
    case High = 'high';

    public function label(): string
    {
        return ucfirst($this->value).' level';
    }

    public function color(): string
    {
        return $this === self::High ? 'danger' : 'success';
    }

    public function icon(): string
    {
        return $this === self::High ? 'arrow-up' : 'arrow-down';
    }
}

enum ChoicePlain: string
{
    case FirstThing = 'first_thing';
    case Second = 'second';
}

class ChoiceThing extends Model
{
    protected $table = 'choice_things';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['perks' => 'array', 'tags' => 'array'];
    }
}

class ChoiceCalls
{
    public int $options = 0;
}

class ChoiceThingResource extends Resource
{
    public static ?ChoiceCalls $calls = null;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ChoiceThing::class)->title('kind')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::select('Kind')->options(function () {
                if (self::$calls !== null) {
                    self::$calls->options++;
                }

                return ['a' => 'Alpha', 'b' => 'Beta'];
            })->displayUsingLabels(),
            Field::boolean('Consent')->trueValue('yes')->falseValue('no'),
            Field::boolean('Flag')->nullable(),
            Field::booleanGroup('Perks')->options(['email' => 'Email', 'sms' => 'SMS']),
            Field::multiSelect('Tags')->options(['x', 'y', 'z']),
            Field::country('Country'),
            Field::timezone('Timezone'),
        ];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function choiceOptions(Field $field): array
{
    /** @var list<array<string, mixed>> */
    return $field->jsonSerialize()['options'];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('choice_things');
    Schema::create('choice_things', function (Blueprint $table) {
        $table->id();
        $table->string('kind')->nullable();
        $table->string('consent')->nullable();
        $table->boolean('flag')->nullable();
        $table->json('perks')->nullable();
        $table->json('tags')->nullable();
        $table->string('country')->nullable();
        $table->string('timezone')->nullable();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(ChoiceThingResource::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    ChoiceThingResource::$calls = null;
});

it('builds the documented class and component for each choice factory', function (Field $field, string $class, string $component) {
    expect($field)->toBeInstanceOf($class)
        ->and($field->getComponent())->toBe($component);
})->with([
    'boolean' => [fn () => Field::boolean('A'), BooleanField::class, 'boolean-field'],
    'select' => [fn () => Field::select('A'), SelectField::class, 'select-field'],
    'booleanGroup' => [fn () => Field::booleanGroup('A'), BooleanGroupField::class, 'boolean-group-field'],
    'multiSelect' => [fn () => Field::multiSelect('A'), MultiSelectField::class, 'multi-select-field'],
    'country' => [fn () => Field::country(), SelectField::class, 'select-field'],
    'timezone' => [fn () => Field::timezone(), SelectField::class, 'select-field'],
]);

it('builds a computed, form-hidden field from a closure attribute', function (Field $field) {
    expect($field->isComputed())->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Update))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Index))->toBeTrue();
})->with([
    'boolean' => [fn () => Field::boolean('A', fn () => true)],
    'select' => [fn () => Field::select('A', fn () => 'a')],
]);

it('labels an indexed array by its values and an associative one by its map', function () {
    expect(choiceOptions(Field::select('K')->options(['red', 'blue'])))->toBe([
        ['value' => 'red', 'label' => 'red'],
        ['value' => 'blue', 'label' => 'blue'],
    ])->and(choiceOptions(Field::select('K')->options(['r' => 'Red', 'b' => 'Blue'])))->toBe([
        ['value' => 'r', 'label' => 'Red'],
        ['value' => 'b', 'label' => 'Blue'],
    ]);
});

it('takes label, color and icon from an enum, and humanizes a case name', function () {
    expect(choiceOptions(Field::select('K')->options(ChoiceLevel::class)))->toBe([
        ['value' => 'low', 'label' => 'Low level', 'color' => 'success', 'icon' => 'arrow-down'],
        ['value' => 'high', 'label' => 'High level', 'color' => 'danger', 'icon' => 'arrow-up'],
    ])->and(choiceOptions(Field::select('K')->options(ChoicePlain::class)))->toBe([
        ['value' => 'first_thing', 'label' => 'First Thing'],
        ['value' => 'second', 'label' => 'Second'],
    ]);
});

it('sets the group of the options of an OptionGroup', function () {
    $options = choiceOptions(Field::select('K')->options([
        'none' => 'None',
        OptionGroup::make('Fruit', ['a' => 'Apple', 'p' => 'Pear']),
    ]));

    expect($options)->toBe([
        ['value' => 'none', 'label' => 'None'],
        ['value' => 'a', 'label' => 'Apple', 'group' => 'Fruit'],
        ['value' => 'p', 'label' => 'Pear', 'group' => 'Fruit'],
    ]);
});

it('throws a LogicException naming the field at 1,001 options', function () {
    $many = range(1, 1001);

    expect(fn () => Field::select('Big pick')->options($many)->jsonSerialize())
        ->toThrow(LogicException::class, 'Big pick')
        ->and(fn () => Field::select('Big pick')->options(fn () => $many)->jsonSerialize())
        ->toThrow(LogicException::class, 'Big pick')
        ->and(count(choiceOptions(Field::select('Big pick')->options(range(1, 1000)))))->toBe(1000);
});

it('runs an options closure once for a whole index response', function () {
    ChoiceThing::query()->insert([['kind' => 'a'], ['kind' => 'b'], ['kind' => 'a']]);
    ChoiceThingResource::$calls = new ChoiceCalls;

    LatheTest::resource('choice-things')->index()->assertOk();

    expect(ChoiceThingResource::$calls->options)->toBe(1);
});

it('shows labels on the index cell with displayUsingLabels', function () {
    ChoiceThing::query()->insert([['kind' => 'b']]);

    $response = LatheTest::resource('choice-things')->index()->assertOk();

    expect($response->json('table.rows.0.cells.kind.display'))->toBe('Beta');
});

it('rejects a select value outside its options with 422', function () {
    LatheTest::resource('choice-things')->store(['kind' => 'zzz'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');

    LatheTest::resource('choice-things')->store(['kind' => 'a'])->assertCreated();

    expect(ChoiceThing::query()->value('kind'))->toBe('a');
});

it('writes trueValue or falseValue and keeps null on a nullable boolean', function () {
    LatheTest::resource('choice-things')->store(['consent' => true, 'flag' => true])->assertCreated();
    LatheTest::resource('choice-things')->store(['consent' => false, 'flag' => false])->assertCreated();
    LatheTest::resource('choice-things')->store(['consent' => false, 'flag' => null])->assertCreated();

    expect(ChoiceThing::query()->orderBy('id')->pluck('consent')->all())->toBe(['yes', 'no', 'no'])
        ->and(ChoiceThing::query()->orderBy('id')->pluck('flag')->map(fn ($flag) => $flag === null ? null : (bool) $flag)->all())
        ->toBe([true, false, null]);
});

it('resolves a boolean by its trueValue and defaults to false', function () {
    $field = Field::boolean('Consent')->trueValue('yes')->falseValue('no');

    expect($field->resolve((new ChoiceThing)->forceFill(['consent' => 'yes'])))->toBeTrue()
        ->and($field->resolve((new ChoiceThing)->forceFill(['consent' => 'no'])))->toBeFalse()
        ->and(Field::boolean('Plain')->resolveDefaultValue())->toBeFalse()
        ->and(Field::boolean('Flag')->nullable()->resolve(new ChoiceThing))->toBeNull();
});

it('swaps the switch for a checkbox with asCheckbox', function () {
    expect(Field::boolean('A')->jsonSerialize()['asCheckbox'])->toBeFalse()
        ->and(Field::boolean('A')->asCheckbox()->jsonSerialize()['asCheckbox'])->toBeTrue();
});

it('keeps a boolean group as a JSON object and rejects an unknown key', function () {
    LatheTest::resource('choice-things')->store(['perks' => ['email' => true, 'sms' => false]])->assertCreated();
    LatheTest::resource('choice-things')->store(['perks' => ['fax' => true]])->assertUnprocessable();

    expect(ChoiceThing::query()->value('perks'))->toBe(['email' => true, 'sms' => false]);
});

it('lets hideFalseValues and hideTrueValues exclude each other', function () {
    $group = Field::booleanGroup('P')->options(['email' => 'Email']);

    expect($group->jsonSerialize())->toMatchArray(['hideFalseValues' => false, 'hideTrueValues' => false, 'noValueText' => 'No Data'])
        ->and($group->hideFalseValues()->jsonSerialize())->toMatchArray(['hideFalseValues' => true, 'hideTrueValues' => false])
        ->and(Field::booleanGroup('P')->hideFalseValues()->hideTrueValues()->jsonSerialize())
        ->toMatchArray(['hideFalseValues' => false, 'hideTrueValues' => true]);
});

it('keeps a multi select as a JSON array of its options', function () {
    LatheTest::resource('choice-things')->store(['tags' => ['x', 'z']])->assertCreated();
    LatheTest::resource('choice-things')->store(['tags' => ['x', 'q']])->assertUnprocessable();

    expect(ChoiceThing::query()->value('tags'))->toBe(['x', 'z'])
        ->and(Field::multiSelect('T')->jsonSerialize()['withSelectedTags'])->toBeTrue()
        ->and(Field::multiSelect('T')->withSelectedTags(false)->jsonSerialize()['withSelectedTags'])->toBeFalse()
        ->and(Field::multiSelect('T')->options(['x'])->jsonSerialize()['component'])->toBe('multi-select-field');
});

it('opens a country select on the default, with names from ICU sorted by name', function () {
    $field = Field::country('Country')->default('US');
    $options = choiceOptions($field);
    $names = array_column($options, 'label');
    $sorted = $names;
    sort($sorted);

    expect($field->resolveDefaultValue())->toBe('US')
        ->and($names)->toBe($sorted)
        ->and($options)->toContain(['value' => 'BD', 'label' => 'Bangladesh'])
        ->and($options)->toContain(['value' => 'US', 'label' => 'United States'])
        ->and(array_filter(array_column($options, 'value'), fn ($code) => preg_match('/^[A-Z]{2}$/', $code) !== 1))->toBe([])
        ->and(array_column($options, 'value'))->not->toContain('ZZ')
        ->and($field->jsonSerialize())->toMatchArray(['searchable' => true, 'displayUsingLabels' => true]);
});

it('saves BD when Bangladesh is picked and lists the timezone identifiers', function () {
    LatheTest::resource('choice-things')->store(['country' => 'BD', 'timezone' => 'Asia/Dhaka'])->assertCreated();
    LatheTest::resource('choice-things')->store(['country' => 'XX'])->assertUnprocessable();

    expect(ChoiceThing::query()->value('country'))->toBe('BD')
        ->and(array_column(choiceOptions(Field::timezone()), 'value'))->toBe(timezone_identifiers_list())
        ->and(Field::timezone()->jsonSerialize()['searchable'])->toBeTrue();
});

it('memoizes the country and timezone lists in the request attributes', function () {
    $request = LatheRequest::createFrom(Request::create('/lathe'));
    app()->instance(LatheRequest::class, $request);

    choiceOptions(Field::country());
    choiceOptions(Field::timezone());

    expect($request->attributes->has('lathe.countries'))->toBeTrue()
        ->and($request->attributes->has('lathe.timezones'))->toBeTrue();
});
