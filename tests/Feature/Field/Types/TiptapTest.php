<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Contracts\PreviewsContent;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\TiptapField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class TiptapPosts extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->title('title')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id(),
            Field::text('Title'),
            Field::text('Status'),
            Field::tiptap('Body'),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(TiptapPosts::class);
});

it('builds the tiptap factory hidden from the index', function () {
    $field = Field::tiptap('Body');
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/tiptap-posts'));
    $shown = array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context, $request));

    expect($field)->toBeInstanceOf(TiptapField::class)
        ->and($field)->toBeInstanceOf(PreviewsContent::class)
        ->and($field->getComponent())->toBe('tiptap-field')
        ->and(array_values(array_map(fn (FieldContext $context) => $context->value, $shown)))->toBe(['detail', 'create', 'update']);
});

it('rich_text_is_sanitized_on_write_and_render', function (string $payload, string $clean) {
    $id = LatheTest::resource('tiptap-posts')
        ->store(['title' => $payload, 'status' => 'draft', 'body' => $payload])
        ->assertCreated()
        ->json('id');

    expect(DB::table('posts')->where('id', $id)->value('body'))->toBe($clean);

    LatheTest::resource('tiptap-posts')->update($id, ['title' => $payload, 'status' => 'draft', 'body' => "<p>ok</p>{$payload}"])->assertOk();
    $post = Post::findOrFail($id);

    expect(DB::table('posts')->where('id', $id)->value('body'))->toBe("<p>ok</p>{$clean}")
        ->and(Field::tiptap('Body')->cell($post)['value']->toHtml())->toBe("<p>ok</p>{$clean}")
        ->and(Field::text('Title')->asHtml()->cell($post)['value']->toHtml())->toBe($clean);

    // A value stored before the field cleaned on write is still cleaned on render.
    DB::table('posts')->where('id', $id)->update(['body' => $payload]);

    expect(json_encode(Field::tiptap('Body')->cell($post->refresh())['value']))->toBe(json_encode(['__html' => $clean]));
})->with('xss_payloads')->group('regression');

it('keeps the image and drops onerror when a pasted image is saved', function () {
    $id = LatheTest::resource('tiptap-posts')
        ->store(['title' => 'Hello', 'status' => 'draft', 'body' => '<p>Hi</p><img src=x onerror=alert(1)>'])
        ->assertCreated()
        ->json('id');

    expect(DB::table('posts')->where('id', $id)->value('body'))->toBe('<p>Hi</p><img src="x" />')
        ->and(json_encode(Field::tiptap('Body')->cell(Post::findOrFail($id))))
        ->toBe('{"value":{"__html":"<p>Hi<\/p><img src=\"x\" \/>"},"display":"Hi"}');
});

it('shows one line of display text per block, so long values collapse', function () {
    $html = implode('', array_map(fn (int $line) => "<p>line {$line}</p>", range(1, 11))).'<ul><li>a</li><li>b<br>c</li></ul>';
    $post = Post::create(['title' => 'Hello', 'status' => 'draft', 'body' => $html]);

    expect(Field::tiptap('Body')->cell($post)['display'])
        ->toBe("line 1\nline 2\nline 3\nline 4\nline 5\nline 6\nline 7\nline 8\nline 9\nline 10\nline 11\na\nb\nc")
        ->and(Field::tiptap('Body')->cell(new Post))->toBe(['value' => null, 'display' => '']);
});

it('answers the field preview endpoint with sanitized HTML', function (string $payload, string $clean) {
    $id = Post::create(['title' => 'Hello', 'status' => 'draft', 'body' => 'x'])->id;

    $this->postJson('/lathe/api/resources/tiptap-posts/field/body/preview', ['value' => "<p><strong>bold</strong></p>{$payload}"])
        ->assertOk()
        ->assertExactJson(['preview' => ['__html' => "<p><strong>bold</strong></p>{$clean}"]]);
    $this->postJson("/lathe/api/resources/tiptap-posts/{$id}/field/body/preview", ['value' => $payload])
        ->assertOk()
        ->assertExactJson(['preview' => ['__html' => $clean]]);
})->with('xss_payloads');
