<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class MeterRecord extends Model
{
    protected $table = 'meter_things';

    protected $guarded = [];

    public $timestamps = false;
}

class MeterThings extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MeterRecord::class)->title('stage')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::select('Stage')->options(['a', 'b', 'c', 'd', 'e', 'f'])->meter(),
        ];
    }
}

/**
 * @return array<string, string>
 */
function meterStatuses(): array
{
    return ['pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('meter_things');
    Schema::create('meter_things', function (Blueprint $table) {
        $table->id();
        $table->string('stage')->nullable();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('sends the ticks of a meter in declared order minus except', function () {
    $field = Field::select('Status')->options(meterStatuses())->meter(except: ['cancelled'])->freeze();

    expect($field->jsonSerialize()['meter'])->toBe(['pending', 'paid', 'shipped']);
});

it('reads the options whichever order meter() and options() are called in', function () {
    $field = Field::select('Status')->meter(except: ['cancelled'])->options(meterStatuses())->freeze();

    expect($field->jsonSerialize()['meter'])->toBe(['pending', 'paid', 'shipped']);
});

it('sends no meter and keeps the badges on a select without meter()', function () {
    $field = Field::select('Status')->options(meterStatuses())->badges(['paid' => 'success'])->freeze();

    expect($field->jsonSerialize()['meter'])->toBeNull()
        ->and($field->jsonSerialize()['badges'])->toBe(['paid' => 'success']);
});

it('refuses a meter with too few or too many ticks when the field freezes', function (Field $field) {
    $field->freeze();
})->with([
    'one tick' => [fn () => Field::select('Status')->options(['a', 'b'])->meter(except: ['b'])],
    'six ticks' => [fn () => Field::select('Status')->options(['a', 'b', 'c', 'd', 'e', 'f'])->meter()],
])->throws(\LogicException::class, 'Status');

it('refuses a bad meter when a field that was never frozen is sent, as a lens column or a repeater line is', function (Field $field) {
    $field->jsonSerialize();
})->with([
    'six ticks' => [fn () => Field::select('Stage')->options(['a', 'b', 'c', 'd', 'e', 'f'])->meter()],
    'remote options' => [fn () => Field::select('Owner')->remoteOptions(fn () => User::query(), 'name')->meter()],
])->throws(\LogicException::class, 'The meter of the field [');

it('names the count of ticks in the refusal', function () {
    Field::select('Status')->options(['a', 'b'])->meter(except: ['b'])->freeze();
})->throws(\LogicException::class, 'The meter of the field [Status] needs 2 to 5 ticks; it has 1.');

it('refuses a meter on remote options', function () {
    Field::select('Owner')->remoteOptions(fn () => User::query(), 'name')->meter()->freeze();
})->throws(\LogicException::class, 'Owner');

it('puts the ticks of the workbench order status on its index column', function () {
    $columns = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.columns');
    $status = collect($columns)->firstWhere('label', 'Status');

    expect($status['meter'])->toBe(['pending', 'paid', 'shipped']);
});

it('answers the index of a resource with a bad meter with the exception', function () {
    Lathe::registerResources(MeterThings::class);
    $this->withoutExceptionHandling();

    $this->get('/lathe/resources/meter-things');
})->throws(\LogicException::class, 'The meter of the field [Stage]');
