<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\DateField;
use Lathe\Field\DateTimeField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class DateThing extends Model
{
    protected $table = 'date_things';

    protected $guarded = [];

    protected $casts = ['due_on' => 'date', 'starts_at' => 'datetime'];
}

class BareDateThing extends Model
{
    protected $table = 'date_things';

    protected $guarded = [];
}

class DateThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(DateThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'date-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::date('Due on', 'due_on')->min('2026-01-01')->max('2026-12-31')->nullable(),
            Field::dateTime('Starts at', 'starts_at')->nullable(),
        ];
    }
}

function dateRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/date-things'));
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('date_things');
    Schema::create('date_things', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->date('due_on')->nullable();
        $table->dateTime('starts_at')->nullable();
        $table->timestamps();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(DateThingResource::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('builds the date and dateTime fields from their factories', function () {
    expect(Field::date('Due'))->toBeInstanceOf(DateField::class)
        ->and(Field::date('Due')->getComponent())->toBe('date-field')
        ->and(Field::dateTime('Starts'))->toBeInstanceOf(DateTimeField::class)
        ->and(Field::dateTime('Starts')->getComponent())->toBe('date-time-field');
});

it('travels a date as Y-m-d and a datetime as UTC ISO 8601', function () {
    $thing = new DateThing(['due_on' => '2026-10-14 00:00:00', 'starts_at' => '2026-10-13 19:00:00']);

    expect(Field::date('Due', 'due_on')->resolve($thing))->toBe('2026-10-14')
        ->and(Field::date('Due', 'due_on')->cell($thing)['display'])->toBe('2026-10-14')
        ->and(Field::dateTime('Starts', 'starts_at')->resolve($thing))->toBe('2026-10-13T19:00:00Z')
        ->and(Field::date('Due', 'due_on')->resolve(new DateThing(['due_on' => null])))->toBeNull();
});

it('turns min and max into after_or_equal and before_or_equal rules', function () {
    $rules = Field::date('Due', 'due_on')->min('2026-01-01')->max('2026-12-31')->getRules(FieldContext::Create)['due_on'];
    $timed = Field::dateTime('Starts', 'starts_at')->min('2026-01-01T08:30')->getRules(FieldContext::Create)['starts_at'];

    expect($rules)->toContain('after_or_equal:2026-01-01')->toContain('before_or_equal:2026-12-31')
        ->and($timed)->toContain('after_or_equal:2026-01-01T08:30');

    $tester = LatheTest::resource('date-things');
    $tester->store(['name' => 'A', 'due_on' => '2025-12-31'])->assertUnprocessable()->assertJsonValidationErrors('due_on');
    $tester->store(['name' => 'A', 'due_on' => '2027-01-01'])->assertUnprocessable()->assertJsonValidationErrors('due_on');
    $tester->store(['name' => 'A', 'due_on' => '14/10/2026'])->assertUnprocessable()->assertJsonValidationErrors('due_on');
    $tester->store(['name' => 'A', 'due_on' => '2026-10-14'])->assertCreated();
});

it('throws a LogicException naming a date field on a model without a date cast', function () {
    $field = Field::date('Due', 'due_on');

    expect(fn () => $field->resolve(new BareDateThing(['due_on' => '2026-10-14'])))
        ->toThrow(\LogicException::class, 'due_on');
    expect(fn () => $field->fill(dateRequest()->merge(['due_on' => '2026-10-14']), new BareDateThing))
        ->toThrow(\LogicException::class, 'due_on');
});

it('stores a datetime entered in Pacific/Kiritimati as UTC and returns UTC', function () {
    Lathe::userTimezone(fn () => 'Pacific/Kiritimati');
    $tester = LatheTest::resource('date-things');

    $id = $tester->store(['name' => 'A', 'starts_at' => '2026-10-14T09:00'])->assertCreated()->json('id');
    expect(DB::table('date_things')->where('id', $id)->value('starts_at'))->toBe('2026-10-13 19:00:00')
        ->and(Field::dateTime('Starts', 'starts_at')->resolve(DateThing::findOrFail($id)))->toBe('2026-10-13T19:00:00Z');

    $tester->update($id, ['name' => 'A', 'starts_at' => '2026-10-15T09:00'])->assertOk();
    expect(DB::table('date_things')->where('id', $id)->value('starts_at'))->toBe('2026-10-14 19:00:00');

    $tester->update($id, ['name' => 'A', 'starts_at' => '2026-10-16T19:00:00Z'])->assertOk();
    expect(DB::table('date_things')->where('id', $id)->value('starts_at'))->toBe('2026-10-16 19:00:00');

    $tester->update($id, ['name' => 'A', 'starts_at' => null])->assertOk();
    expect(DB::table('date_things')->where('id', $id)->value('starts_at'))->toBeNull();

    $tester->update($id, ['name' => 'A', 'starts_at' => 'tomorrow-ish'])->assertUnprocessable()->assertJsonValidationErrors('starts_at');
});

it('sends the local wall time of a datetime with the form schema', function () {
    Lathe::userTimezone(fn () => 'Pacific/Kiritimati');
    $thing = new DateThing(['starts_at' => '2026-10-13 19:00:00']);
    $schema = Field::dateTime('Starts', 'starts_at')->schema(FieldContext::Update, dateRequest(), $thing);

    expect($schema['localValue'])->toBe('2026-10-14T09:00');
});
