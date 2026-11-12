<?php

namespace Lathe\Tests\Feature\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Export\ExportAction;
use Lathe\Actions\Import\Import;
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Support\JobContext;
use Workbench\Database\Factories\UserFactory;

class DatedThing extends Model
{
    protected $table = 'dated_things';

    protected $guarded = [];

    protected $casts = ['released_on' => 'date', 'happened_at' => 'datetime'];
}

class DatedThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(DatedThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'dated-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::date('Released', 'released_on')->nullable(),
            Field::dateTime('Happened', 'happened_at')->nullable(),
        ];
    }

    public function actions(LatheRequest $request): array
    {
        return [ExportAction::make()];
    }
}

/**
 * The rows of the CSV an export of every dated thing downloads, as a user in the zone.
 *
 * @return list<list<string|null>>
 */
function exportDated(string $zone): array
{
    Lathe::userTimezone(fn () => $zone);
    Gate::define('viewLathe', fn () => true);
    test()->actingAs(UserFactory::new()->create());

    $url = test()->postJson('/lathe/api/resources/dated-things/action', ['action' => 'export', 'selection' => ['mode' => 'all'], 'fields' => ['filename' => 'dated']])
        ->assertOk()->json('result.effect.url');
    $content = (string) test()->get($url)->assertOk()->streamedContent();
    $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;

    return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($content)));
}

/**
 * Import the CSV into dated_things as a user in the zone.
 */
function importDated(string $csv, string $zone): void
{
    $user = UserFactory::new()->create();
    $file = 'lathe-imports/'.Str::ulid().'.csv';
    Storage::disk('local')->put($file, $csv);
    $import = Import::create([
        'user_id' => (string) $user->id,
        'file_disk' => 'local',
        'file_name' => $file,
        'original_name' => 'dated.csv',
        'model_class' => DatedThing::class,
        'column_map' => [0 => 'name', 1 => 'released_on', 2 => 'happened_at'],
    ]);

    ImportAction::make()->withModel(DatedThing::class)
        ->withColumns(ImportColumn::make('name'), ImportColumn::make('released_on')->date()->nullable(), ImportColumn::make('happened_at')->date()->nullable())
        ->start($import, 'import', new JobContext($user->id, 'web', 'en', $zone));
}

/**
 * @return array<string, mixed>
 */
function storedDated(): array
{
    return (array) DB::table('dated_things')->select('released_on', 'happened_at')->first();
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Storage::fake('local');
    Schema::create('dated_things', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->date('released_on')->nullable();
        $table->dateTime('happened_at')->nullable();
        $table->timestamps();
    });
    Lathe::registerResources(DatedThingResource::class);
});

it('stores a date-only cell as its calendar day for a user in any zone', function (string $zone) {
    importDated("Name,Released,Happened\nOne,2026-03-08,\n", $zone);

    expect(DatedThing::firstOrFail()->released_on?->toDateString())->toBe('2026-03-08')
        ->and(substr((string) storedDated()['released_on'], 0, 10))->toBe('2026-03-08');
})->with(['Asia/Tokyo', 'Pacific/Kiritimati', 'America/Los_Angeles']);

it('stores a datetime cell as the app wall time of the moment the user meant, and lists it at that moment', function (string $app, string $user, string $stored, string $moment) {
    appTimezone($app);

    importDated("Name,Released,Happened\nOne,,2026-03-08 09:00\n", $user);
    Lathe::userTimezone(fn () => $user);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(UserFactory::new()->create());

    expect(substr((string) storedDated()['happened_at'], 0, 19))->toBe($stored)
        ->and($this->getJson('/lathe/api/resources/dated-things')->assertOk()->json('table.rows.0.cells.happened_at.value'))->toBe($moment);
})->with([
    'Berlin app, Berlin user' => ['Europe/Berlin', 'Europe/Berlin', '2026-03-08 09:00:00', '2026-03-08T08:00:00Z'],
    'UTC app, Dhaka user' => ['UTC', 'Asia/Dhaka', '2026-03-08 03:00:00', '2026-03-08T03:00:00Z'],
]);

it('stores an imported datetime as the text a form saves for the same entry', function () {
    appTimezone('Europe/Berlin');
    Lathe::userTimezone(fn () => 'Asia/Dhaka');
    $request = LatheRequest::createFrom(Request::create('/', 'POST', ['happened_at' => '2026-03-08 09:00']));
    $form = new DatedThing(['name' => 'Form']);
    Field::dateTime('Happened', 'happened_at')->fill($request, $form);

    importDated("Name,Released,Happened\nOne,,2026-03-08 09:00\n", 'Asia/Dhaka');

    expect(storedDated()['happened_at'])->toBe($form->getAttributes()['happened_at']);
});

it('exports a date-only column as its calendar day and a datetime in the user zone', function () {
    DB::table('dated_things')->insert(['name' => 'One', 'released_on' => '2026-03-08', 'happened_at' => '2026-03-08 09:00:00']);

    expect(exportDated('America/Los_Angeles'))->toBe([['Name', 'Released', 'Happened'], ['One', '2026-03-08', '2026-03-08 01:00:00']]);
});

it('leaves a date-only and a datetime value unchanged through an export and an import by the same user', function () {
    appTimezone('Europe/Berlin');
    DB::table('dated_things')->insert(['name' => 'One', 'released_on' => '2026-03-08', 'happened_at' => '2026-03-08 09:00:00']);
    $before = storedDated();
    $rows = exportDated('America/Los_Angeles');
    DB::table('dated_things')->delete();

    importDated(implode("\n", array_map(fn (array $row) => implode(',', $row), $rows))."\n", 'America/Los_Angeles');

    expect(DatedThing::firstOrFail()->released_on?->toDateString())->toBe('2026-03-08')
        ->and(substr((string) storedDated()['happened_at'], 0, 19))->toBe($before['happened_at']);
});
