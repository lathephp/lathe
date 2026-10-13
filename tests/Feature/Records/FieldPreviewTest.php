<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class PreviewPosts extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->title('title')->canonical(false)->allowAll();
    }

    /** Archived posts are outside the edit scope. */
    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('status', '!=', 'archived');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Title'),
            Field::markdown('Body'),
            Field::markdown('Secret', 'status')->canSee(fn () => false),
        ];
    }
}

/** PostPolicy has no create or update method, so both are denied. */
/** Views posts but never creates or updates one. */
class LockedPostPolicy
{
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, mixed $post): bool
    {
        return true;
    }
}

class LockedPosts extends Resource
{
    public static ?string $policy = LockedPostPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->title('title')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::markdown('Body')];
    }
}

function previewPost(string $status = 'draft'): Post
{
    return Post::create(['title' => 'Hello', 'status' => $status, 'body' => 'x']);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(PreviewPosts::class, LockedPosts::class);
});

it('previews a markdown value on the create and the update form', function () {
    $id = previewPost()->id;

    $this->postJson('/lathe/api/resources/preview-posts/field/body/preview', ['value' => '**bold**'])
        ->assertOk()
        ->assertExactJson(['preview' => ['__html' => "<p><strong>bold</strong></p>\n"]]);
    $this->postJson("/lathe/api/resources/preview-posts/{$id}/field/body/preview", ['value' => '# Hi'])
        ->assertOk()
        ->assertExactJson(['preview' => ['__html' => "<h1>Hi</h1>\n"]]);
    $this->postJson('/lathe/api/resources/preview-posts/field/body/preview', ['value' => ''])
        ->assertOk()
        ->assertExactJson(['preview' => ['__html' => '']]);
});

it('markdown_preview_is_sanitized', function (string $payload) {
    $body = $this->postJson('/lathe/api/resources/preview-posts/field/body/preview', ['value' => $payload])
        ->assertOk()
        ->getContent();

    expect($body)->toBe(match ($payload) {
        '<a href="javascript:alert(1)">x</a>', '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>' => '{"preview":{"__html":"<p>x<\/p>\n"}}',
        default => '{"preview":{"__html":""}}',
    });
})->with('xss_payloads')->group('regression');

it('refuses a value over 262,144 bytes', function () {
    $url = '/lathe/api/resources/preview-posts/field/body/preview';

    $this->postJson($url, ['value' => str_repeat('a', 262_144)])->assertOk();
    $this->postJson($url, ['value' => str_repeat('a', 262_145)])->assertUnprocessable()->assertJsonValidationErrors('value');
    // 131,073 characters of two bytes each: under the limit in characters, over it in bytes.
    $this->postJson($url, ['value' => str_repeat('é', 131_073)])->assertUnprocessable()->assertJsonValidationErrors('value');
});

it('answers 404 for a field that does not preview, is unknown or is hidden', function () {
    $id = previewPost()->id;

    $this->postJson('/lathe/api/resources/preview-posts/field/nope/preview', ['value' => 'x'])->assertNotFound();
    $this->postJson('/lathe/api/resources/preview-posts/field/title/preview', ['value' => 'x'])->assertNotFound();
    $this->postJson('/lathe/api/resources/preview-posts/field/status/preview', ['value' => 'x'])->assertNotFound();
    $this->postJson("/lathe/api/resources/preview-posts/{$id}/field/title/preview", ['value' => 'x'])->assertNotFound();
});

it('needs create or update on the found record', function () {
    $id = previewPost()->id;

    $this->postJson('/lathe/api/resources/locked-posts/field/body/preview', ['value' => 'x'])->assertForbidden();
    $this->postJson("/lathe/api/resources/locked-posts/{$id}/field/body/preview", ['value' => 'x'])->assertForbidden();
    $this->postJson('/lathe/api/resources/preview-posts/'.previewPost('archived')->id.'/field/body/preview', ['value' => 'x'])->assertNotFound();
});
