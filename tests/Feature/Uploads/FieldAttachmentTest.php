<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Lathe\Uploads\FieldAttachment;
use Lathe\Uploads\PendingUpload;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(AttachmentPosts::class);
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('s3');
    $this->actingAs(faAdmin());
});

/** Everyone may write a post; only the admin may update one. */
class AttachmentPostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function delete(User $user, Post $post): bool
    {
        return true;
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return true;
    }
}

/** Posts with a Tiptap body on s3 and a Markdown title on the storage disk; "Hidden" posts are out of scope. */
class AttachmentPosts extends Resource
{
    public static ?string $policy = AttachmentPostPolicy::class;

    public static function uriKey(): string
    {
        return 'attachment-posts';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Status'),
            Field::markdown('Title', 'title')->withFiles(),
            Field::tiptap('Body', 'body')->withFiles('s3'),
        ];
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('title', '!=', 'Hidden');
    }

    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('title', '!=', 'Hidden');
    }
}

function faAdmin(): User
{
    return User::where('email', 'admin@lathephp.com')->firstOrFail();
}

function faEditor(): User
{
    return User::where('email', 'editor@lathephp.com')->firstOrFail();
}

/** A 1x1 PNG, so finfo reads image/png. */
function faPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

function faDraftId(string $field = 'body'): string
{
    return (string) test()->getJson("/lathe/api/resources/attachment-posts/field-attachment/{$field}/draft-id")->assertOk()->json('draft_id');
}

/**
 * @param  array<string, mixed>  $body
 */
function faStart(array $body, string $field = 'body'): TestResponse
{
    return test()->postJson("/lathe/api/resources/attachment-posts/uploads/{$field}", $body + ['name' => 'pasted.png', 'size' => strlen(faPng()), 'mime' => 'image/png']);
}

/**
 * The editor's upload: start with the draft id, one chunk, complete, then attach.
 *
 * @return array{id: string, url: string}
 */
function faUpload(string $draftId, string $field = 'body'): array
{
    $id = (string) faStart(['draft_id' => $draftId], $field)->assertCreated()->json('upload_id');
    test()->call('PUT', "/lathe/api/uploads/{$id}/chunks/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], faPng())->assertNoContent();
    test()->postJson("/lathe/api/uploads/{$id}/complete")->assertOk();
    $url = (string) test()->postJson("/lathe/api/resources/attachment-posts/field-attachment/{$field}", ['upload_id' => $id])->assertOk()->json('url');

    return ['id' => $id, 'url' => $url];
}

function faPost(string $title = 'Visible'): Post
{
    return Post::create(['title' => $title, 'status' => 'draft', 'body' => '<p>Hi</p>']);
}

function faAttachment(Post $post, string $path = 'kept.png'): FieldAttachment
{
    Storage::disk('s3')->put($path, faPng());

    return FieldAttachment::create([
        'attachable_type' => $post->getMorphClass(),
        'attachable_id' => (string) $post->getKey(),
        'attachment' => $path,
        'disk' => 's3',
        'url' => Storage::disk('s3')->url($path),
    ]);
}

it('answers a server UUID as the draft id of a Tiptap and a Markdown field with files', function (string $field) {
    $first = faDraftId($field);

    expect($first)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and(faDraftId($field))->not->toBe($first);

    // A field without files has no drafts.
    $this->getJson('/lathe/api/resources/attachment-posts/field-attachment/status/draft-id')->assertNotFound();
})->with(['tiptap body' => 'body', 'markdown title' => 'title']);

it('starts an editor upload on the withFiles disk whatever disk the body names', function () {
    $id = (string) faStart(['draft_id' => faDraftId(), 'disk' => 'public', 'directory' => 'evil'])->assertCreated()->json('upload_id');

    expect(PendingUpload::findOrFail($id)->only(['disk', 'path', 'field']))->toBe(['disk' => 's3', 'path' => '', 'field' => 'body']);
});

it('refuses an editor upload without a draft id or that is not a raster image', function (array $body) {
    faStart($body)->assertUnprocessable();
})->with([
    'no draft id' => [[]],
    'an svg' => [['draft_id' => '0b4f1c1e-5d2c-4a52-9c39-0d1e6a3b7f10', 'name' => 'x.svg', 'mime' => 'image/svg+xml']],
]);

it('stores an attached image on the field disk and answers its URL', function () {
    $upload = faUpload(faDraftId());
    $path = (string) PendingUpload::findOrFail($upload['id'])->path;

    expect(Storage::disk('s3')->get($path))->toBe(faPng())
        ->and($upload['url'])->toBe(Storage::disk('s3')->url($path))
        ->and($upload['url'])->toStartWith('/storage/');
});

it("moves the caller's attached uploads of the draft into the field attachments on save", function () {
    $draftId = faDraftId();
    $upload = faUpload($draftId);
    $path = (string) PendingUpload::findOrFail($upload['id'])->path;
    $other = faUpload(faDraftId());

    $id = LatheTest::resource('attachment-posts')
        ->store(['title' => 'Pictures', 'status' => 'draft', 'body' => '<p><img src="'.$upload['url'].'"></p>', 'bodyDraftId' => $draftId])
        ->assertCreated()
        ->json('id');

    expect(FieldAttachment::all()->map->only(['attachable_type', 'attachable_id', 'attachment', 'disk', 'url'])->all())->toBe([[
        'attachable_type' => (new Post)->getMorphClass(),
        'attachable_id' => (string) $id,
        'attachment' => $path,
        'disk' => 's3',
        'url' => $upload['url'],
    ]])
        ->and(PendingUpload::find($upload['id']))->toBeNull()
        ->and(PendingUpload::find($other['id']))->not->toBeNull()
        ->and(Post::findOrFail($id)->body)->toBe('<p><img src="'.$upload['url'].'" /></p>');
});

it('promotes nothing from a draft id another user created', function () {
    $this->actingAs(faEditor());
    $draftId = faDraftId();
    $upload = faUpload($draftId);

    $this->actingAs(faAdmin());
    LatheTest::resource('attachment-posts')
        ->store(['title' => 'Stolen', 'status' => 'draft', 'body' => '<p>x</p>', 'bodyDraftId' => $draftId])
        ->assertCreated();

    expect(FieldAttachment::count())->toBe(0)
        ->and(PendingUpload::find($upload['id']))->not->toBeNull();
});

it('deletes an attachment by its id: the file and the row', function () {
    $attachment = faAttachment(faPost());

    $this->deleteJson('/lathe/api/resources/attachment-posts/field-attachment/body', ['attachment_id' => $attachment->id])->assertNoContent();

    expect(FieldAttachment::find($attachment->id))->toBeNull()
        ->and(Storage::disk('s3')->exists('kept.png'))->toBeFalse();
});

it('keeps an attachment without update on its record, out of scope or of another type', function (string $case, int $status) {
    $post = faPost($case === 'out of scope' ? 'Hidden' : 'Visible');
    $attachment = faAttachment($post);

    if ($case === 'without update') {
        $this->actingAs(faEditor());
    }

    if ($case === 'another type') {
        $attachment->update(['attachable_type' => (new User)->getMorphClass()]);
    }

    $this->deleteJson('/lathe/api/resources/attachment-posts/field-attachment/body', ['attachment_id' => $attachment->id])->assertStatus($status);

    expect(FieldAttachment::find($attachment->id))->not->toBeNull()
        ->and(Storage::disk('s3')->exists('kept.png'))->toBeTrue();
})->with([
    ['without update', 403],
    ['out of scope', 404],
    ['another type', 404],
]);

it("removes only the caller's pending uploads of a draft", function () {
    $draftId = faDraftId();
    $mine = faUpload($draftId);
    $minePath = (string) PendingUpload::findOrFail($mine['id'])->path;

    $this->actingAs(faEditor());
    $theirs = (string) faStart(['draft_id' => $draftId])->assertCreated()->json('upload_id');

    $this->actingAs(faAdmin());
    $this->deleteJson("/lathe/api/resources/attachment-posts/field-attachment/body/{$draftId}")->assertNoContent();

    expect(PendingUpload::find($mine['id']))->toBeNull()
        ->and(Storage::disk('s3')->exists($minePath))->toBeFalse()
        ->and(PendingUpload::find($theirs))->not->toBeNull();
});

it('deletes the attachments of a force deleted record and keeps those of a soft deleted one', function () {
    $post = faPost();
    $attachment = faAttachment($post);

    $this->deleteJson("/lathe/api/resources/attachment-posts/{$post->id}")->assertOk();

    expect(FieldAttachment::find($attachment->id))->not->toBeNull()
        ->and(Storage::disk('s3')->exists('kept.png'))->toBeTrue();

    $this->deleteJson("/lathe/api/resources/attachment-posts/{$post->id}/force")->assertOk();

    expect(FieldAttachment::find($attachment->id))->toBeNull()
        ->and(Storage::disk('s3')->exists('kept.png'))->toBeFalse();
});
