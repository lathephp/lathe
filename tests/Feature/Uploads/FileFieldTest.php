<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Lathe\Uploads\PendingUpload;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Schema::create('file_docs', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('file')->nullable();
        $table->string('photo')->nullable();
        $table->json('items')->nullable();
        $table->softDeletes();
    });
    Lathe::registerResources(FilePeople::class, HardFileDocs::class, SoftFileDocs::class, HookedFileDocs::class);
    Storage::fake('local');
    Storage::fake('public');
    FileFieldLog::$entries = [];
    $this->actingAs(ffAdmin());
});

/** Collects what the field closures and the model events saw. */
class FileFieldLog
{
    /** @var list<mixed> */
    public static array $entries = [];
}

class FileDoc extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'file_docs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}

class HardFileDoc extends Model
{
    public $timestamps = false;

    protected $table = 'file_docs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}

/** Users with a prunable avatar that keeps its name and size; only the admin is in scope. */
class FilePeople extends Resource
{
    public static function uriKey(): string
    {
        return 'file-people';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(User::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name')->rules('filled'),
            Field::make('Email'),
            Field::file('Avatar', 'avatar')->prunable()->storeOriginalName('avatar_name')->storeSize('avatar_size'),
        ];
    }

    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('email', 'admin@lathephp.com');
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('email', 'admin@lathephp.com');
    }
}

class HardFileDocs extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(HardFileDoc::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::file('File', 'file')->prunable(),
            Field::file('Photo', 'photo')->prunable(),
            Field::file('Items', 'items')->prunable(),
        ];
    }
}

class SoftFileDocs extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(FileDoc::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::file('File', 'file')->prunable(),
            Field::file('Photo', 'photo')->prunable(),
        ];
    }
}

/** Every Closure of a file field replaced. */
class HookedFileDocs extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(HardFileDoc::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::file('File', 'file')->disk('local')->path('named')->prunable()
                ->storeAs(fn (UploadedFile $file) => 'kept-'.$file->getClientOriginalName())
                ->preview(fn (string $path, string $disk) => "preview:{$disk}:{$path}")
                ->thumbnail(fn (string $path, string $disk) => "thumb:{$disk}:{$path}")
                ->download(fn ($request, $model, $disk, $path) => response("custom {$disk}:{$path}")),
            Field::file('Photo', 'photo')->prunable()->disableDownload()->deletable(false)
                ->store(function ($request, $file, $model, $attribute, $disk, $path) {
                    FileFieldLog::$entries[] = "store {$attribute} {$disk}:{$path}";

                    return $file->storeAs('by-closure', 'photo.png', 'local');
                })
                ->delete(function ($request, $model, $disk, $path) {
                    FileFieldLog::$entries[] = "delete {$disk}:{$path}";
                }),
        ];
    }
}

function ffAdmin(): User
{
    return User::where('email', 'admin@lathephp.com')->firstOrFail();
}

function ffEditor(): User
{
    return User::where('email', 'editor@lathephp.com')->firstOrFail();
}

/** A 1x1 PNG. */
function ffPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

/**
 * A completed upload of the admin, its assembled file waiting on the local disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function ffUpload(array $attributes = []): PendingUpload
{
    $upload = PendingUpload::create($attributes + [
        'user_id' => (string) ffAdmin()->getKey(),
        'field' => 'avatar',
        'disk' => 'public',
        'path' => '',
        'original_name' => 'me.png',
        'size' => strlen(ffPng()),
        'status' => PendingUpload::COMPLETE,
    ]);

    Storage::disk('local')->put($upload->folder().'/assembled', ffPng());

    return $upload;
}

/** The admin holding an avatar on the public disk. */
function ffAdminWithAvatar(string $path = 'old.png'): User
{
    Storage::disk('public')->put($path, 'old bytes');
    $admin = ffAdmin();
    $admin->forceFill(['avatar' => $path, 'avatar_name' => 'Old Name.png', 'avatar_size' => 9])->save();

    return $admin;
}

// Fill

it('stores an upload under 40 random characters on the storage disk and drops the pending row', function (?string $storageDisk, string $lands) {
    if ($storageDisk !== null) {
        config(['lathe.storage_disk' => $storageDisk]);
    }

    $upload = ffUpload();

    LatheTest::resource('users')->update(ffAdmin()->getKey(), ['avatar' => $upload->id])->assertOk();

    $path = (string) ffAdmin()->avatar;

    expect($path)->toMatch('/^[A-Za-z0-9]{40}\.png$/')
        ->and(Storage::disk($lands)->get($path))->toBe(ffPng())
        ->and(PendingUpload::find($upload->id))->toBeNull()
        ->and(Storage::disk('local')->exists($upload->folder()))->toBeFalse();
})->with([
    'default storage_disk' => [null, 'public'],
    'storage_disk flipped to local' => ['local', 'local'],
]);

it('fills the original name and size columns', function () {
    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['avatar' => ffUpload()->id])->assertOk();

    expect(ffAdmin()->only(['avatar_name', 'avatar_size']))->toBe(['avatar_name' => 'me.png', 'avatar_size' => 68]);
});

it('keeps the pending row and the old file when the save fails', function () {
    ffAdminWithAvatar();
    $upload = ffUpload();
    User::saving(fn () => throw new RuntimeException('save failed'));

    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['avatar' => $upload->id])->assertServerError();

    expect(PendingUpload::find($upload->id))->not->toBeNull()
        ->and(Storage::disk('local')->get($upload->folder().'/assembled'))->toBe(ffPng())
        ->and(Storage::disk('public')->get('old.png'))->toBe('old bytes')
        ->and(ffAdmin()->avatar)->toBe('old.png');
});

it('answers 422 on the field for an upload the user may not attach', function (string $case) {
    $upload = ffUpload(match ($case) {
        'another user' => ['user_id' => (string) ffEditor()->getKey()],
        'uploading' => ['status' => PendingUpload::PENDING],
        'another field' => ['field' => 'resume'],
        'missing' => ['id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ'],
    });
    $id = $case === 'missing' ? '01JYYYYYYYYYYYYYYYYYYYYYYY' : $upload->id;

    LatheTest::resource('users')->update(ffAdmin()->getKey(), ['avatar' => $id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar');

    expect(ffAdmin()->avatar)->toBeNull()
        ->and(PendingUpload::find($upload->id))->not->toBeNull();
})->with(['another user', 'uploading', 'another field', 'missing']);

it('stores with storeAs, previews, thumbnails and downloads through their Closures', function () {
    $response = LatheTest::resource('hooked-file-docs')->store(['name' => 'A', 'file' => ffUpload(['field' => 'file'])->id])->assertCreated();
    $id = $response->json('id');

    expect(HardFileDoc::findOrFail($id)->file)->toBe('named/kept-me.png')
        ->and(Storage::disk('local')->get('named/kept-me.png'))->toBe(ffPng());

    $cell = LatheTest::resource('hooked-file-docs')->index()->assertOk()->json('table.rows.0.cells.file');

    expect($cell['value'])->toMatchArray([
        'path' => 'named/kept-me.png',
        'name' => 'kept-me.png',
        'preview' => 'preview:local:named/kept-me.png',
        'thumbnail' => 'thumb:local:named/kept-me.png',
        'downloadable' => true,
    ]);

    $this->get("/lathe/api/resources/hooked-file-docs/{$id}/download/file")->assertOk()->assertContent('custom local:named/kept-me.png');
});

it('stores and deletes through the store and delete Closures', function () {
    $doc = HardFileDoc::create(['name' => 'A', 'photo' => 'old-photo.png']);

    LatheTest::resource('hooked-file-docs')->update($doc->getKey(), ['photo' => ffUpload(['field' => 'photo'])->id])->assertOk();

    expect($doc->fresh()?->photo)->toBe('by-closure/photo.png')
        ->and(Storage::disk('local')->get('by-closure/photo.png'))->toBe(ffPng())
        ->and(FileFieldLog::$entries)->toBe(['store photo public:', 'delete public:old-photo.png']);
});

it('shows the disk URL in the index cell without touching the disk', function () {
    ffAdminWithAvatar('pic.png');
    $fake = Storage::disk('public');
    assert($fake instanceof FilesystemAdapter);
    Storage::set('public', new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter
    {
        public function exists($path): bool
        {
            FileFieldLog::$entries[] = "exists {$path}";

            return parent::exists($path);
        }

        public function fileExists($path): bool
        {
            FileFieldLog::$entries[] = "fileExists {$path}";

            return parent::fileExists($path);
        }
    });

    $cell = collect(LatheTest::resource('file-people')->index()->assertOk()->json('table.rows'))
        ->pluck('cells.avatar')
        ->firstWhere('value.path', 'pic.png');

    expect($cell)->toMatchArray([
        'value' => [
            'path' => 'pic.png',
            'name' => 'Old Name.png',
            'size' => 9,
            'preview' => '/storage/pic.png',
            'thumbnail' => '/storage/pic.png',
            'downloadable' => true,
        ],
        'display' => 'Old Name.png',
    ])->and(FileFieldLog::$entries)->toBe([]);
});

// Replace and remove

it('stores the new file before it deletes the old one, after the save', function () {
    ffAdminWithAvatar();
    User::saved(function (User $user) {
        FileFieldLog::$entries[] = [
            'old' => Storage::disk('public')->exists('old.png'),
            'new' => Storage::disk('public')->exists((string) $user->avatar),
        ];
    });

    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['avatar' => ffUpload()->id])->assertOk();

    expect(FileFieldLog::$entries)->toBe([['old' => true, 'new' => true]])
        ->and(Storage::disk('public')->exists('old.png'))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([ffAdmin()->avatar]);
});

it('removes the file and nulls its columns when the field is deletable and prunable', function () {
    ffAdminWithAvatar();

    // The workbench avatar is not prunable: the columns go, the file stays.
    LatheTest::resource('users')->update(ffAdmin()->getKey(), ['avatar' => null, 'avatar_removed' => 1])->assertOk();

    expect(ffAdmin()->avatar)->toBeNull()
        ->and(Storage::disk('public')->exists('old.png'))->toBeTrue();

    ffAdminWithAvatar();
    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['avatar' => null, 'avatar_removed' => 1])->assertOk();

    expect(ffAdmin()->only(['avatar', 'avatar_name', 'avatar_size']))->toBe(['avatar' => null, 'avatar_name' => null, 'avatar_size' => null])
        ->and(Storage::disk('public')->exists('old.png'))->toBeFalse();
});

it('leaves the file alone when the form sends no new upload and no removal', function () {
    ffAdminWithAvatar();

    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['avatar' => null])->assertOk();

    expect(ffAdmin()->avatar)->toBe('old.png')
        ->and(Storage::disk('public')->exists('old.png'))->toBeTrue();
});

it('deletes nothing when validation fails', function () {
    ffAdminWithAvatar();
    $upload = ffUpload();

    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['name' => '', 'avatar' => null, 'avatar_removed' => 1])->assertUnprocessable();
    LatheTest::resource('file-people')->update(ffAdmin()->getKey(), ['name' => '', 'avatar' => $upload->id])->assertUnprocessable();

    expect(ffAdmin()->avatar)->toBe('old.png')
        ->and(Storage::disk('public')->allFiles())->toBe(['old.png'])
        ->and(PendingUpload::find($upload->id))->not->toBeNull();
});

// Record deletion

it('deletes the prunable files of a record without soft deletes', function () {
    Storage::disk('public')->put('a.png', 'a');
    Storage::disk('public')->put('b.png', 'b');
    $doc = HardFileDoc::create(['name' => 'A', 'file' => 'a.png', 'photo' => 'b.png']);

    LatheTest::resource('hard-file-docs')->delete($doc->getKey())->assertOk();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps the files of a soft deleted record until it is force deleted', function () {
    Storage::disk('public')->put('a.png', 'a');
    Storage::disk('public')->put('b.png', 'b');
    $doc = FileDoc::create(['name' => 'A', 'file' => 'a.png', 'photo' => 'b.png']);

    LatheTest::resource('soft-file-docs')->delete($doc->getKey())->assertOk();

    expect(Storage::disk('public')->allFiles())->toBe(['a.png', 'b.png']);

    LatheTest::resource('soft-file-docs')->forceDelete($doc->getKey())->assertOk();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

// Download

it('downloads under the original name, else the basename', function (string $resource, string $name) {
    ffAdminWithAvatar('avatars/abc.png');

    $response = $this->get("/lathe/api/resources/{$resource}/".ffAdmin()->getKey().'/download/avatar')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toBe("attachment; filename={$name}")
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->streamedContent())->toBe('old bytes');
})->with([
    'original name column' => ['file-people', '"Old Name.png"'],
    'basename' => ['users', 'abc.png'],
]);

it('answers 403 to a download without view', function () {
    ffAdminWithAvatar();
    Gate::before(fn ($user, string $ability) => $ability === 'view' ? false : null);

    $this->get('/lathe/api/resources/users/'.ffAdmin()->getKey().'/download/avatar')->assertForbidden();
});

it('answers 404 to a download of a missing file, a disabled download and a cross-scope id', function () {
    $admin = ffAdmin();
    $admin->forceFill(['avatar' => 'gone.png'])->save();
    Storage::disk('local')->put('p.png', 'p');
    $doc = HardFileDoc::create(['name' => 'A', 'photo' => 'p.png']);
    ffEditor()->forceFill(['avatar' => 'e.png'])->save();
    Storage::disk('public')->put('e.png', 'e');

    $this->get("/lathe/api/resources/users/{$admin->getKey()}/download/avatar")->assertNotFound();
    $this->get("/lathe/api/resources/hooked-file-docs/{$doc->getKey()}/download/photo")->assertNotFound();
    $this->get('/lathe/api/resources/file-people/'.ffEditor()->getKey().'/download/avatar')->assertNotFound();
    $this->get('/lathe/api/resources/users/'.ffEditor()->getKey().'/download/avatar')->assertOk();
});

// Destroy

it('deletes the file and nulls its columns, leaving every other attribute', function () {
    ffAdminWithAvatar();
    $before = ffAdmin()->only(['name', 'email', 'password']);

    $this->deleteJson('/lathe/api/resources/file-people/'.ffAdmin()->getKey().'/field/avatar')->assertNoContent();

    expect(ffAdmin()->only(['avatar', 'avatar_name', 'avatar_size']))->toBe(['avatar' => null, 'avatar_name' => null, 'avatar_size' => null])
        ->and(ffAdmin()->only(['name', 'email', 'password']))->toEqual($before)
        ->and(Storage::disk('public')->exists('old.png'))->toBeFalse();
});

it('answers 403 to a destroy without update and 404 for a field that is not deletable or out of scope', function () {
    ffAdminWithAvatar();
    $doc = HardFileDoc::create(['name' => 'A', 'photo' => 'p.png']);
    Storage::disk('public')->put('p.png', 'p');

    $this->deleteJson("/lathe/api/resources/hooked-file-docs/{$doc->getKey()}/field/photo")->assertNotFound();
    $this->deleteJson('/lathe/api/resources/file-people/'.ffEditor()->getKey().'/field/avatar')->assertNotFound();

    Gate::before(fn ($user, string $ability) => $ability === 'update' ? false : null);
    $this->deleteJson('/lathe/api/resources/users/'.ffAdmin()->getKey().'/field/avatar')->assertForbidden();

    expect(ffAdmin()->avatar)->toBe('old.png')
        ->and(Storage::disk('public')->allFiles())->toBe(['old.png', 'p.png']);
});

it('finds a dotted path in the stored record', function () {
    Storage::disk('public')->put('two.png', '2');
    Storage::disk('public')->put('zero.png', '0');
    $items = [['photo' => 'zero.png', 'title' => 'a'], ['photo' => null, 'title' => 'b'], ['photo' => 'two.png', 'title' => 'c']];
    $doc = HardFileDoc::create(['name' => 'A', 'items' => $items]);

    $this->deleteJson("/lathe/api/resources/hard-file-docs/{$doc->getKey()}/field/items.2.photo")->assertNoContent();

    expect($doc->fresh()?->items)->toBe([['photo' => 'zero.png', 'title' => 'a'], ['photo' => null, 'title' => 'b'], ['photo' => null, 'title' => 'c']])
        ->and($doc->fresh()?->name)->toBe('A')
        ->and(Storage::disk('public')->allFiles())->toBe(['zero.png']);
});
