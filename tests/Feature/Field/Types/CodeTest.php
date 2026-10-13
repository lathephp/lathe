<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\CodeField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class CodeSample extends Model
{
    protected $table = 'code_samples';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['settings' => 'array'];
}

class CodeSamples extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(CodeSample::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id(),
            Field::text('Name'),
            Field::code('Settings')->json(),
            Field::code('Snippet'),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::create('code_samples', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->json('settings')->nullable();
        $table->text('snippet')->nullable();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(CodeSamples::class);
});

it('builds the code factory hidden from the index', function () {
    $field = Field::code('Snippet');
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/code-samples'));
    $shown = array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context, $request));

    expect($field)->toBeInstanceOf(CodeField::class)
        ->and($field->getComponent())->toBe('code-field')
        ->and(array_values(array_map(fn (FieldContext $context) => $context->value, $shown)))->toBe(['detail', 'create', 'update']);
});

it('sends the language, height and editor options in the payload', function () {
    $payload = json_decode((string) json_encode(
        Field::code('Snippet')->language('php')->height(300)->fullHeight()->autoHeight()->editorOptions(['tabSize' => 4])->editorOptions(['lineWrapping' => true]),
    ), true);

    expect($payload)->toMatchArray([
        'component' => 'code-field',
        'language' => 'php',
        'height' => 300,
        'fullHeight' => true,
        'autoHeight' => true,
        'editorOptions' => ['tabSize' => 4, 'lineWrapping' => true],
    ])
        ->and(json_decode((string) json_encode(Field::code('Snippet')), true))->toMatchArray([
            'language' => 'plaintext', 'height' => null, 'fullHeight' => false, 'autoHeight' => false, 'editorOptions' => [],
        ])
        ->and(json_decode((string) json_encode(Field::code('Settings')->json()), true)['language'])->toBe('json');
});

it('code_field_json_round_trips', function () {
    $id = LatheTest::resource('code-samples')->store(['name' => 'a', 'settings' => '{"a":1}'])->assertCreated()->json('id');

    expect(CodeSample::findOrFail($id)->settings)->toBe(['a' => 1]);

    LatheTest::resource('code-samples')->update($id, ['name' => 'a', 'settings' => '{"title":"Zoë \\u00e9","n":[1,2]}'])->assertSuccessful();

    expect(CodeSample::findOrFail($id)->settings)->toBe(['title' => 'Zoë é', 'n' => [1, 2]])
        ->and(Field::code('Settings')->json()->resolve(CodeSample::findOrFail($id)))
        ->toBe("{\n    \"title\": \"Zoë é\",\n    \"n\": [\n        1,\n        2\n    ]\n}");

    LatheTest::resource('code-samples')->store(['name' => 'b', 'settings' => '{a:'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings');

    LatheTest::resource('code-samples')->update($id, ['name' => 'a', 'settings' => '{a:'])->assertUnprocessable()->assertJsonValidationErrors('settings');

    expect(CodeSample::findOrFail($id)->settings)->toBe(['title' => 'Zoë é', 'n' => [1, 2]]);
})->group('regression');

it('keeps plain code as typed', function () {
    $id = LatheTest::resource('code-samples')->store(['name' => 'a', 'snippet' => '<?php echo "é";'])->assertCreated()->json('id');

    expect(CodeSample::findOrFail($id)->snippet)->toBe('<?php echo "é";')
        ->and(Field::code('Snippet')->cell(CodeSample::findOrFail($id)))->toBe(['value' => '<?php echo "é";', 'display' => '<?php echo "é";']);
});

it('honours custom json flags', function () {
    $record = new CodeSample(['settings' => ['a' => 'é']]);

    expect(Field::code('Settings')->json(JSON_UNESCAPED_UNICODE)->resolve($record))->toBe('{"a":"é"}')
        ->and(Field::code('Settings')->json(0)->resolve($record))->toBe('{"a":"\\u00e9"}');
});
