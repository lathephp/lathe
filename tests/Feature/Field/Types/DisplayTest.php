<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Enums\Icon;
use Lathe\Field\BadgeField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\IconField;
use Lathe\Field\LineField;
use Lathe\Field\SparklineField;
use Lathe\Field\StackField;
use Lathe\Field\StatusField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

enum DisplayState: string
{
    case Paid = 'paid';
    case Pending = 'pending';

    public function label(): string
    {
        return strtoupper($this->value);
    }
}

enum DisplayPlain: string
{
    case OnHold = 'on_hold';
}

class DisplayRecord extends Model
{
    protected $table = 'display_things';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['state' => DisplayState::class];
    }
}

class DisplayThing extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(DisplayRecord::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'display-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::badge('State')->map(['paid' => 'success', 'pending' => 'warning']),
            Field::badge('Stage')->nullLabel('No stage'),
            Field::stack('Who', 'name', [
                Field::line('Name')->asHeading(),
                Field::line('Stage')->asSmall()->extraClasses('text-red-500'),
                fn (Model $record) => 'row '.$record->getKey(),
            ]),
        ];
    }
}

/**
 * A record with the given attributes, never saved.
 *
 * @param  array<string, mixed>  $attributes
 */
function displayRecord(array $attributes = []): DisplayRecord
{
    return (new DisplayRecord)->forceFill($attributes);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('display_things');
    Schema::create('display_things', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('state')->nullable();
        $table->string('stage')->nullable();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(DisplayThing::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('builds the documented class, component and contexts for each display factory', function (Field $field, string $class, string $component) {
    expect($field)->toBeInstanceOf($class)
        ->and($field->getComponent())->toBe($component)
        ->and($field->isShownOn(FieldContext::Index))->toBeTrue()
        ->and($field->isShownOn(FieldContext::Detail))->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Update))->toBeFalse();
})->with([
    'badge' => [fn () => Field::badge('A'), BadgeField::class, 'badge-field'],
    'status' => [fn () => Field::status('A'), StatusField::class, 'status-field'],
    'icon' => [fn () => Field::icon('A'), IconField::class, 'icon-field'],
    'sparkline' => [fn () => Field::sparkline('A'), SparklineField::class, 'sparkline-field'],
    'stack' => [fn () => Field::stack('A'), StackField::class, 'stack-field'],
    'line' => [fn () => Field::line('A'), LineField::class, 'line-field'],
]);

it('builds a computed field from a closure attribute', function (Field $field) {
    expect($field->isComputed())->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Index))->toBeTrue();
})->with([
    'badge' => [fn () => Field::badge('A', fn () => 'x')],
    'icon' => [fn () => Field::icon('A', fn () => 'x')],
    'status' => [fn () => Field::status('A', fn () => 'x')],
    'line' => [fn () => Field::line('A', fn () => 'x')],
]);

it('maps a value to a badge type', function () {
    $badge = Field::badge('State')->map(['paid' => 'success', 'pending' => 'warning']);

    expect($badge->cell(displayRecord(['state' => 'paid'])))->toMatchArray(['display' => 'PAID', 'type' => 'success'])
        ->and($badge->cell(displayRecord(['state' => 'pending']))['type'])->toBe('warning')
        ->and($badge->cell(displayRecord(['state' => 'other']))['type'])->toBe('info');
});

it('labels a badge by closure, then nullLabel, then labels, then the enum label, then the humanized value', function () {
    $badge = Field::badge('Stage');

    expect($badge->labelUsing(fn (mixed $value) => "closure {$value}")->labels(['a' => 'Alpha'])->nullLabel('None')->cell(displayRecord(['stage' => 'a']))['display'])->toBe('closure a')
        ->and(Field::badge('Stage')->labels(['a' => 'Alpha'])->nullLabel('None')->cell(displayRecord(['stage' => 'a']))['display'])->toBe('Alpha')
        ->and(Field::badge('Stage')->labels(['a' => 'Alpha'])->nullLabel('None')->cell(displayRecord(['stage' => null]))['display'])->toBe('None')
        ->and(Field::badge('State')->labels(['paid' => 'Settled'])->cell(displayRecord(['state' => 'paid']))['display'])->toBe('Settled')
        ->and(Field::badge('State')->cell(displayRecord(['state' => 'paid']))['display'])->toBe('PAID')
        ->and(Field::badge('Stage')->cell(displayRecord(['stage' => 'pending']))['display'])->toBe('Pending')
        ->and(Field::badge('Stage')->cell(displayRecord(['stage' => 'on_hold']))['display'])->toBe('On Hold');
});

it('keeps a custom badge type and lists the declared ones', function () {
    $badge = Field::badge('Stage')->types(['vip'])->map(['gold' => 'vip']);

    expect($badge->cell(displayRecord(['stage' => 'gold']))['type'])->toBe('vip')
        ->and($badge->jsonSerialize()['types'])->toBe(['vip']);
});

it('maps types to icons with withIcons', function () {
    $badge = Field::badge('Stage')->withIcons()->map(['a' => 'success', 'b' => 'info', 'c' => 'danger', 'd' => 'warning', 'e' => 'vip']);
    $icon = fn (string $value) => $badge->cell(displayRecord(['stage' => $value]))['icon'] ?? null;

    expect([$icon('a'), $icon('b'), $icon('c'), $icon('d'), $icon('e')])->toBe(['circle-check', 'info', 'circle-alert', 'triangle-alert', null])
        ->and(Field::badge('Stage')->map(['a' => 'success'])->cell(displayRecord(['stage' => 'a'])))->not->toHaveKey('icon')
        ->and(Field::badge('Stage')->map(['a' => 'vip'])->withIcons(['vip' => 'crown'])->cell(displayRecord(['stage' => 'a']))['icon'])->toBe('crown');
});

it('badge_over_enum_cast_column_renders', function () {
    DisplayRecord::query()->insert(['name' => 'One', 'state' => 'paid']);

    $row = LatheTest::resource('display-things')->index()->assertOk()->json('table.rows.0.cells');

    expect($row['state'])->toMatchArray(['value' => 'paid', 'display' => 'PAID', 'type' => 'success']);
})->group('regression');

it('badge_null_label_renders', function () {
    DisplayRecord::query()->insert(['name' => 'One']);

    $row = LatheTest::resource('display-things')->index()->assertOk()->json('table.rows.0.cells');

    expect($row['stage'])->toMatchArray(['value' => null, 'display' => 'No stage']);
})->group('regression');

it('gives a status the type loading, failed or success', function () {
    $status = Field::status('Stage')->loadingWhen(['queued', 'running'])->failedWhen('failed');
    $type = fn (string $value) => $status->cell(displayRecord(['stage' => $value]))['type'];

    expect([$type('queued'), $type('running'), $type('failed'), $type('done')])->toBe(['loading', 'loading', 'failed', 'success'])
        ->and(Field::status('Stage')->cell(displayRecord(['stage' => null]))['type'])->toBe('success');
});

it('status_field_types_follow_enum', function () {
    $status = Field::status('State')->loadingWhen(DisplayState::Pending)->failedWhen([DisplayState::Paid]);

    expect($status->cell(displayRecord(['state' => DisplayState::Pending]))['type'])->toBe('loading')
        ->and($status->cell(displayRecord(['state' => 'paid']))['type'])->toBe('failed')
        ->and($status->cell(displayRecord(['state' => DisplayState::Paid]))['display'])->toBe('PAID');
})->group('regression');

it('status_match_is_strict_for_0_4_and_true', function () {
    $zero = Field::status('Score')->failedWhen(0);
    $one = Field::status('Score')->failedWhen(1);

    expect($zero->cell(displayRecord(['score' => 0.4]))['type'])->toBe('success')
        ->and($zero->cell(displayRecord(['score' => 0]))['type'])->toBe('failed')
        ->and($one->cell(displayRecord(['score' => true]))['type'])->toBe('success')
        ->and($one->cell(displayRecord(['score' => 1]))['type'])->toBe('failed');
})->group('regression');

it('compares badge map keys strictly too', function () {
    $badge = Field::badge('Score')->map([0 => 'danger', 1 => 'success']);

    expect($badge->cell(displayRecord(['score' => 0.4]))['type'])->toBe('info')
        ->and($badge->cell(displayRecord(['score' => true]))['type'])->toBe('info')
        ->and($badge->cell(displayRecord(['score' => 1]))['type'])->toBe('success')
        ->and($badge->cell(displayRecord(['score' => '0']))['type'])->toBe('danger');
});

it('maps a value to an icon and a color token, with a fallback', function () {
    $icon = Field::icon('Stage')->map(['a' => 'star', 'b' => Icon::Check])->colors(['a' => 'warning'])->fallback('circle');

    expect($icon->cell(displayRecord(['stage' => 'a'])))->toMatchArray(['icon' => 'star', 'color' => 'warning'])
        ->and($icon->cell(displayRecord(['stage' => 'b'])))->toMatchArray(['icon' => 'check', 'color' => null])
        ->and($icon->cell(displayRecord(['stage' => 'zzz']))['icon'])->toBe('circle')
        ->and(Field::icon('Stage')->cell(displayRecord(['stage' => 'zzz']))['icon'])->toBeNull();
});

it('gives a sparkline the default size and one shared series from an iterable or a widget', function () {
    $widget = new class
    {
        /** @return list<int> */
        public function series(): array
        {
            return [3, 1, 2];
        }
    };

    expect(Field::sparkline('Trend')->jsonSerialize())->toMatchArray(['height' => 50, 'width' => 100, 'asBarChart' => false])
        ->and(Field::sparkline('Trend')->height(20)->width(60)->asBarChart()->jsonSerialize())->toMatchArray(['height' => 20, 'width' => 60, 'asBarChart' => true])
        ->and(Field::sparkline('Trend')->data([1, 2, 3])->cell(displayRecord(['id' => 1]))['value'])->toBe([1, 2, 3])
        ->and(Field::sparkline('Trend')->data($widget)->cell(displayRecord(['id' => 2]))['value'])->toBe([3, 1, 2])
        ->and(Field::sparkline('Trend')->data(fn () => [5, 6])->cell(displayRecord(['id' => 3]))['value'])->toBe([5, 6]);
});

it('runs a sparkline closure once for a whole page', function () {
    $calls = 0;
    $field = Field::sparkline('Trend')->data(function (Collection $records) use (&$calls) {
        $calls++;

        return $records->mapWithKeys(fn (Model $record) => [$record->getKey() => [$record->getKey(), 2]])->all();
    });
    $records = new Collection(array_map(fn (int $id) => displayRecord(['id' => $id]), range(1, 100)));

    $field->primeForPage($records);
    $series = $records->map(fn (Model $record) => $field->cell($record)['value']);

    expect($calls)->toBe(1)
        ->and($series[6])->toBe([7, 2])
        ->and($series)->toHaveCount(100);
});

it('resolves a stack of lines from the same record with no extra queries', function () {
    DisplayRecord::query()->insert([['name' => 'One', 'stage' => 'pending']]);
    DB::enableQueryLog();
    LatheTest::resource('display-things')->index()->assertOk();
    $single = count(DB::getQueryLog());

    DisplayRecord::query()->insert(array_map(fn (int $i) => ['name' => "Row {$i}", 'stage' => 'pending'], range(1, 99)));
    DB::flushQueryLog();
    $response = LatheTest::resource('display-things')->index()->assertOk();

    expect(count(DB::getQueryLog()))->toBe($single)
        ->and($response->json('table.rows'))->toHaveCount(100);
});

it('turns a stack closure into an anonymous line and keeps each line style and classes', function () {
    DisplayRecord::query()->insert(['name' => 'One', 'stage' => 'pending']);

    $lines = LatheTest::resource('display-things')->index()->assertOk()->json('table.rows.0.cells.who.value');

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toMatchArray(['display' => 'One', 'style' => 'heading', 'extraClasses' => ''])
        ->and($lines[1])->toMatchArray(['display' => 'pending', 'style' => 'small', 'extraClasses' => 'text-red-500'])
        ->and($lines[2])->toMatchArray(['display' => 'row 1', 'style' => 'base']);
});

it('sets the line text style', function (string $method, string $style) {
    $line = Field::line('A')->{$method}();

    expect($line->cell(displayRecord(['a' => 'x'])))->toMatchArray(['display' => 'x', 'style' => $style])
        ->and($line->jsonSerialize()['style'])->toBe($style);
})->with([
    ['asHeading', 'heading'],
    ['asSubTitle', 'subtitle'],
    ['asSmall', 'small'],
    ['asBase', 'base'],
]);

it('accepts extra classes as a list or a string', function () {
    expect(Field::line('A')->extraClasses(['a', 'b'])->jsonSerialize()['extraClasses'])->toBe('a b')
        ->and(Field::line('A')->extraClasses('c d')->jsonSerialize()['extraClasses'])->toBe('c d');
});
