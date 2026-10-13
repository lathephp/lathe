<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\MarkdownField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class MarkdownPosts extends Resource
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
            Field::markdown('Body'),
        ];
    }
}

/** What Markdown leaves of each xss_payloads input: raw HTML is stripped, never shown as text. */
const MARKDOWN_XSS = [
    '<script>alert(1)</script>' => '',
    '<img src=x onerror=alert(1)>' => '',
    '<a href="javascript:alert(1)">x</a>' => "<p>x</p>\n",
    '<svg onload=alert(1)>' => '',
    '<iframe src="https://example.com">' => '',
    '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>' => "<p>x</p>\n",
    '<style>p{}</style>' => '',
    '<p style="color:red">x</p>' => '',
];

function markdownPost(string $body): Post
{
    return Post::create(['title' => 'Hello', 'status' => 'draft', 'body' => $body]);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(MarkdownPosts::class);
});

it('builds the markdown factory hidden from the index', function () {
    $field = Field::markdown('Body');
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/markdown-posts'));
    $shown = array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context, $request));

    expect($field)->toBeInstanceOf(MarkdownField::class)
        ->and($field->getComponent())->toBe('markdown-field')
        ->and(array_values(array_map(fn (FieldContext $context) => $context->value, $shown)))->toBe(['detail', 'create', 'update']);
});

it('markdown_strips_script_and_unsafe_links', function (string $payload, string $sanitized, string $preset) {
    $field = Field::markdown('Body')->preset($preset);

    expect($field->previewFor($payload)->toHtml())->toBe(MARKDOWN_XSS[$payload])
        ->and($field->cell(markdownPost($payload))['value']->toHtml())->toBe(MARKDOWN_XSS[$payload])
        ->and($field->previewFor('[x](javascript:alert(1))')->toHtml())->toBe("<p><a>x</a></p>\n");
})->with('xss_payloads')->with(['default', 'commonmark', 'zero'])->group('regression');

it('renders the detail value as sanitized HTML and keeps the source on the form', function () {
    $field = Field::markdown('Body');
    $post = markdownPost("**bold**\n\n![a](https://example.com/a.png)");

    expect(json_encode($field->cell($post)))->toBe(json_encode([
        'value' => ['__html' => "<p><strong>bold</strong></p>\n<p><img src=\"https://example.com/a.png\" alt=\"a\" /></p>\n"],
        'display' => 'bold',
    ]))
        ->and($field->resolve($post))->toBe("**bold**\n\n![a](https://example.com/a.png)")
        ->and($field->cell(new Post))->toBe(['value' => null, 'display' => '']);
});

it('renders each preset differently', function () {
    $markdown = "**bold** ~~gone~~\n\n| a |\n|---|\n| 1 |";

    expect(Field::markdown('Body')->previewFor($markdown)->toHtml())
        ->toBe("<p><strong>bold</strong> <del>gone</del></p>\n<table>\n<thead>\n<tr>\n<th>a</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>1</td>\n</tr>\n</tbody>\n</table>\n")
        ->and(Field::markdown('Body')->preset('commonmark')->previewFor($markdown)->toHtml())
        ->toBe("<p><strong>bold</strong> ~~gone~~</p>\n<p>| a |\n|---|\n| 1 |</p>\n")
        ->and(Field::markdown('Body')->preset('zero')->previewFor("**bold** [site](https://example.com)  \nnext")->toHtml())
        ->toBe("<p>**bold** <a href=\"https://example.com\">site</a><br />\nnext</p>\n")
        ->and(fn () => Field::markdown('Body')->preset('gfm'))->toThrow(\InvalidArgumentException::class, 'gfm');
});

it('saves the typed source and shows no script on detail', function () {
    $id = LatheTest::resource('markdown-posts')
        ->store(['title' => 'Hello', 'status' => 'draft', 'body' => '<script>alert(1)</script>'])
        ->assertCreated()
        ->json('id');

    $post = Post::findOrFail($id);
    $cell = (string) json_encode(Field::markdown('Body')->cell($post));

    expect(DB::table('posts')->where('id', $id)->value('body'))->toBe('<script>alert(1)</script>')
        ->and($cell)->toBe('{"value":{"__html":""},"display":""}')
        ->and($cell)->not->toContain('<script')
        ->and($cell)->not->toContain('alert(1)');

    LatheTest::resource('markdown-posts')->updateFields($id)
        ->assertOk()
        ->assertJsonPath('fields.2.key', 'body')
        ->assertJsonPath('fields.2.value', '<script>alert(1)</script>');
});

it('never shows the text of an inline script or style', function (string $source) {
    $html = Field::markdown('Body')->previewFor($source)->toHtml();

    expect($html)->not->toContain('alert(1)')->not->toContain('<script')->not->toContain('p{}')
        ->and($html)->toContain('Hi');
})->with([
    'inline' => ['Hi <script>alert(1)</script> there'],
    'unclosed' => ["Hi\n\n<script>alert(1)"],
    'upper case' => ['Hi <SCRIPT type="x">alert(1)</SCRIPT>'],
    'style' => ['Hi <style>p{}</style> there'],
    'own line' => ["Hi\n\n<script>alert(1)</script>\n"],
]);
