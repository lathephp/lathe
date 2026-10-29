<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lathe\Field\Field;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Uploads\PendingUpload;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    Lathe::registerResources(UploadPeople::class);
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/** Lets staff upload to an existing person but never create one. */
class UploadPeoplePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return true;
    }
}

class UploadPeople extends Resource
{
    public static ?string $policy = UploadPeoplePolicy::class;

    public static function uriKey(): string
    {
        return 'upload-people';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(User::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            FileField::make('Resume', 'resume')->disk('local')->path('resumes')->acceptedTypes('application/pdf'),
            FileField::make('Secret', 'secret')->canSee(fn () => false),
        ];
    }
}

/** A 1x1 PNG, so finfo reads image/png. */
function pngBytes(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

/**
 * @param  array<string, mixed>  $body
 */
function startAvatar(array $body = []): TestResponse
{
    return test()->postJson('/lathe/api/resources/users/uploads/avatar', $body + ['name' => 'me.png', 'size' => strlen(pngBytes()), 'mime' => 'image/png']);
}

function startedAvatar(int $size): string
{
    return (string) startAvatar(['size' => $size])->assertCreated()->json('upload_id');
}

/** An upload through the custom field route, whose limit is 10 MB. */
function startedCustom(int $size, string $name = 'a.txt'): string
{
    return (string) test()->postJson('/lathe/api/uploads', ['name' => $name, 'size' => $size, 'mime' => 'text/plain'])->assertCreated()->json('upload_id');
}

function putChunk(string $id, int $index, string $bytes): TestResponse
{
    return test()->call('PUT', "/lathe/api/uploads/{$id}/chunks/{$index}", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes);
}

it('starts an upload and answers its ULID and the chunk size', function () {
    $response = startAvatar()->assertCreated();

    $id = (string) $response->json('upload_id');

    expect($response->json())->toBe(['upload_id' => $id, 'chunk_size' => 5242880])
        ->and(Str::isUlid($id))->toBeTrue()
        ->and(PendingUpload::findOrFail($id)->only(['user_id', 'field', 'disk', 'path', 'original_name', 'size', 'status']))->toBe([
            'user_id' => (string) User::where('email', 'admin@lathephp.com')->value('id'),
            'field' => 'avatar',
            'disk' => 'public',
            'path' => '',
            'original_name' => 'me.png',
            'size' => 68,
            'status' => 'pending',
        ]);
});

it('answers 404 for a field the schema does not hold as a file field', function (string $field) {
    $this->postJson("/lathe/api/resources/users/uploads/{$field}", ['name' => 'a.png', 'size' => 10, 'mime' => 'image/png'])->assertNotFound();
    startAvatar()->assertCreated();

    expect(PendingUpload::count())->toBe(1);
})->with(['unknown' => 'nope', 'not a file field' => 'email']);

it('answers 404 for a file field canSee hides', function () {
    $id = User::where('email', 'admin@lathephp.com')->value('id');

    $this->postJson('/lathe/api/resources/upload-people/uploads/secret', ['name' => 'a.pdf', 'size' => 10, 'mime' => 'application/pdf', 'resource_id' => $id])->assertNotFound();
    $this->postJson('/lathe/api/resources/upload-people/uploads/resume', ['name' => 'a.pdf', 'size' => 10, 'mime' => 'application/pdf', 'resource_id' => $id])->assertCreated();
});

it('finds the record through the scoped query and checks the ability', function () {
    $body = ['name' => 'cv.pdf', 'size' => 10, 'mime' => 'application/pdf'];
    $id = User::where('email', 'admin@lathephp.com')->value('id');

    $this->postJson('/lathe/api/resources/upload-people/uploads/resume', $body + ['resource_id' => 999999])->assertNotFound();
    $this->postJson('/lathe/api/resources/upload-people/uploads/resume', $body)->assertForbidden();
    $this->postJson('/lathe/api/resources/upload-people/uploads/resume', $body + ['resource_id' => $id])->assertCreated();
});

it('refuses a declared size above maxSize', function () {
    startAvatar(['size' => 5 * 1024 * 1024 + 1])->assertUnprocessable()->assertJsonValidationErrors('size');
    startAvatar(['size' => 5 * 1024 * 1024])->assertCreated();
});

it('refuses a declared type outside acceptedTypes', function () {
    $id = User::where('email', 'admin@lathephp.com')->value('id');
    $start = fn (string $name, string $mime) => $this->postJson('/lathe/api/resources/upload-people/uploads/resume', ['name' => $name, 'size' => 10, 'mime' => $mime, 'resource_id' => $id]);

    $start('cv.png', 'image/png')->assertUnprocessable()->assertJsonValidationErrors('mime');
    $start('cv.pdf', 'application/pdf')->assertCreated();
});

it('stores a chunk of 5,242,880 bytes', function () {
    $id = startedAvatar(5242880);

    putChunk($id, 0, str_repeat('a', 5242880))->assertNoContent();

    expect(Storage::disk('local')->size("lathe-uploads/{$id}/0"))->toBe(5242880)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('stores a chunk sent twice once', function () {
    $id = startedAvatar(10);

    putChunk($id, 0, str_repeat('a', 10))->assertNoContent();
    putChunk($id, 0, str_repeat('a', 10))->assertNoContent();

    expect(Storage::disk('local')->allFiles("lathe-uploads/{$id}"))->toBe(["lathe-uploads/{$id}/0"]);
});

it('refuses a chunk of 5,242,881 bytes and stores nothing', function () {
    $id = startedCustom(5242881);

    putChunk($id, 0, str_repeat('a', 5242881))->assertUnprocessable();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a chunk past the declared size', function () {
    $id = startedAvatar(10);

    putChunk($id, 1, 'a')->assertUnprocessable();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('lists the received chunks so a client can resume', function () {
    $id = startedCustom(5242880 + 10);

    putChunk($id, 1, str_repeat('b', 10))->assertNoContent();
    putChunk($id, 0, str_repeat('a', 5242880))->assertNoContent();

    $this->getJson("/lathe/api/uploads/{$id}")->assertOk()->assertExactJson(['size' => 5242890, 'received' => [0, 1]]);
});

it('completes an upload whose content matches its type', function () {
    $id = startedAvatar(68);
    putChunk($id, 0, pngBytes())->assertNoContent();

    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertOk()
        ->assertExactJson(['upload_id' => $id, 'name' => 'me.png', 'size' => 68]);

    expect(PendingUpload::findOrFail($id)->status)->toBe('complete')
        ->and(Storage::disk('local')->get("lathe-uploads/{$id}/assembled"))->toBe(pngBytes());
});

it('refuses a .png that holds PHP code and removes its chunks', function () {
    $php = '<?php system($_GET["c"]); ?>';
    $id = startedAvatar(strlen($php));
    putChunk($id, 0, $php)->assertNoContent();

    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertUnprocessable();

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(PendingUpload::findOrFail($id)->status)->toBe('pending');
});

// libmagic reads a CSV of a few short lines as text/plain, and only longer ones as text/csv.
it('completes a small CSV whose content reads as plain text', function () {
    $csv = "name,price\nA,1\nB,2\n";
    $id = (string) $this->postJson('/lathe/api/uploads', ['name' => 'products.csv', 'size' => strlen($csv), 'mime' => 'text/csv'])->assertCreated()->json('upload_id');
    putChunk($id, 0, $csv)->assertNoContent();

    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertOk();
});

it('refuses a .csv that holds PHP code', function () {
    $php = '<?php system($_GET["c"]); ?>';
    $id = (string) $this->postJson('/lathe/api/uploads', ['name' => 'products.csv', 'size' => strlen($php), 'mime' => 'text/csv'])->assertCreated()->json('upload_id');
    putChunk($id, 0, $php)->assertNoContent();

    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertUnprocessable();
});

it('refuses to complete an upload missing bytes', function () {
    $id = startedCustom(5242880 + 10);
    putChunk($id, 1, str_repeat('a', 10))->assertNoContent();

    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertUnprocessable();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('lets the owner cancel an upload', function () {
    $id = startedAvatar(10);
    putChunk($id, 0, str_repeat('a', 10))->assertNoContent();

    $this->deleteJson("/lathe/api/uploads/{$id}")->assertNoContent();

    expect(PendingUpload::find($id))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it("answers 404 for another user's upload on every verb", function () {
    $id = startedAvatar(10);
    putChunk($id, 0, str_repeat('a', 10))->assertNoContent();

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson("/lathe/api/uploads/{$id}")->assertNotFound();
    putChunk($id, 0, str_repeat('b', 10))->assertNotFound();
    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertNotFound();
    $this->deleteJson("/lathe/api/uploads/{$id}")->assertNotFound();

    expect(PendingUpload::find($id))->not->toBeNull()
        ->and(Storage::disk('local')->get("lathe-uploads/{$id}/0"))->toBe(str_repeat('a', 10));
});

it('upload_ignores_client_disk_and_directory', function () {
    $id = User::where('email', 'admin@lathephp.com')->value('id');

    $upload = (string) $this->postJson('/lathe/api/resources/upload-people/uploads/resume', [
        'name' => 'cv.pdf', 'size' => 10, 'mime' => 'application/pdf', 'resource_id' => $id, 'disk' => 's3', 'directory' => '../../x',
    ])->assertCreated()->json('upload_id');

    expect(PendingUpload::findOrFail($upload)->only(['disk', 'path']))->toBe(['disk' => 'local', 'path' => 'resumes']);
})->group('regression');

it('stores a field with no disk on the storage_disk config', function () {
    $id = startAvatar(['disk' => 's3'])->json('upload_id');

    expect(PendingUpload::findOrFail($id)->disk)->toBe('public');

    config(['lathe.storage_disk' => 'local']);
    $id = startAvatar()->json('upload_id');

    expect(PendingUpload::findOrFail($id)->disk)->toBe('local');
});

it('stores a custom field upload on storage_disk in the uploads directory', function () {
    $response = $this->postJson('/lathe/api/uploads', ['name' => 'notes.txt', 'size' => 10, 'mime' => 'text/plain', 'disk' => 's3', 'directory' => '../../x'])->assertCreated();

    expect($response->json('chunk_size'))->toBe(5242880)
        ->and(PendingUpload::findOrFail($response->json('upload_id'))->only(['field', 'disk', 'path']))->toBe(['field' => '', 'disk' => 'public', 'path' => 'uploads']);
});
