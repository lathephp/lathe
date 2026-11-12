<?php

namespace Lathe\Tests\Feature\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Import\Import;
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\JobContext;
use Workbench\Database\Factories\UserFactory;

class DatedThing extends Model
{
    protected $table = 'dated_things';

    protected $guarded = [];

    protected $casts = ['released_on' => 'date', 'happened_at' => 'datetime'];
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
});

it('stores a date-only cell as its calendar day for a user in any zone', function (string $zone) {
    importDated("Name,Released,Happened\nOne,2026-03-08,\n", $zone);

    expect(DatedThing::firstOrFail()->released_on?->toDateString())->toBe('2026-03-08')
        ->and(storedDated()['released_on'])->toBe((new DatedThing(['released_on' => '2026-03-08']))->getAttributes()['released_on']);
})->with(['Asia/Tokyo', 'Pacific/Kiritimati', 'America/Los_Angeles']);

it('stores a datetime cell as the app wall time of the moment the user meant', function (string $app, string $user, string $stored) {
    appTimezone($app);

    importDated("Name,Released,Happened\nOne,,2026-03-08 09:00\n", $user);

    expect(substr((string) storedDated()['happened_at'], 0, 19))->toBe($stored);
})->with([
    'Berlin app, Berlin user' => ['Europe/Berlin', 'Europe/Berlin', '2026-03-08 09:00:00'],
    'UTC app, Dhaka user' => ['UTC', 'Asia/Dhaka', '2026-03-08 03:00:00'],
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
