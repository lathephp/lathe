<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\KeyValueField;
use Lathe\Field\SlugField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SlugSample extends Model
{
    protected $table = 'slug_samples';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];
}

class SlugSamples extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(SlugSample::class)->title('title')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id(),
            Field::text('Title'),
            Field::slug('Slug')->from('title'),
            Field::slug('Code')->separator('_'),
            Field::keyValue('Meta'),
        ];
    }
}

function slugRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/slug-samples'));
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::create('slug_samples', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('code')->nullable();
        $table->json('meta')->nullable();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(SlugSamples::class);
});

it('builds the slug and key-value factories with their contexts', function () {
    $shown = fn (Field $field) => array_values(array_map(
        fn (FieldContext $context) => $context->value,
        array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context, slugRequest())),
    ));

    expect(Field::slug('Slug'))->toBeInstanceOf(SlugField::class)
        ->and(Field::slug('Slug')->getComponent())->toBe('slug-field')
        ->and($shown(Field::slug('Slug')))->toBe(['index', 'detail', 'create', 'update'])
        ->and(Field::keyValue('Meta'))->toBeInstanceOf(KeyValueField::class)
        ->and(Field::keyValue('Meta')->getComponent())->toBe('key-value-field')
        ->and($shown(Field::keyValue('Meta')))->toBe(['detail', 'create', 'update']);
});

it('saves the framework slug of the typed value with the field separator', function () {
    $id = LatheTest::resource('slug-samples')->store(['title' => 'x', 'slug' => 'Hello World', 'code' => 'Hello World'])->assertSuccessful()->json('id');

    expect(SlugSample::findOrFail($id)->only(['slug', 'code']))->toBe(['slug' => 'hello-world', 'code' => 'hello_world']);
});

it('sends the source, separator and customize button in the payload', function () {
    $field = Field::slug('Slug')->from('title')->separator('_')->showCustomizeButton();

    expect($field->jsonSerialize())->toMatchArray(['from' => 'title', 'separator' => '_', 'showCustomizeButton' => true])
        ->and(Field::slug('Slug')->from(Field::text('Post title'))->jsonSerialize()['from'])->toBe('post_title')
        ->and(Field::slug('Slug')->jsonSerialize())->toMatchArray(['from' => null, 'separator' => '-', 'showCustomizeButton' => false]);
});

it('follows the source only on create and locks only on update behind the button', function () {
    $plain = Field::slug('Slug')->from('title');
    $locked = Field::slug('Slug')->from('title')->showCustomizeButton();

    expect($plain->schema(FieldContext::Create, slugRequest()))->toMatchArray(['follow' => true, 'locked' => false])
        ->and($plain->schema(FieldContext::Update, slugRequest()))->toMatchArray(['follow' => false, 'locked' => false])
        ->and($locked->schema(FieldContext::Create, slugRequest()))->toMatchArray(['follow' => true, 'locked' => false])
        ->and($locked->schema(FieldContext::Update, slugRequest()))->toMatchArray(['follow' => false, 'locked' => true]);
});

it('sends the key-value labels and switches in the payload', function () {
    expect(Field::keyValue('Meta')->jsonSerialize())->toMatchArray([
        'keyLabel' => 'Key',
        'valueLabel' => 'Value',
        'actionText' => 'Add row',
        'disableEditingKeys' => false,
        'disableAddingRows' => false,
        'disableDeletingRows' => false,
        'noValueText' => 'No Data',
    ])->and(Field::keyValue('Meta')->keyLabel('Header')->valueLabel('Content')->actionText('Add header')
        ->disableAddingRows()->disableDeletingRows()->noValueText('Nothing')->jsonSerialize())->toMatchArray([
            'keyLabel' => 'Header',
            'valueLabel' => 'Content',
            'actionText' => 'Add header',
            'disableAddingRows' => true,
            'disableDeletingRows' => true,
            'noValueText' => 'Nothing',
        ]);
});

it('locks the keys from true and from a Closure over the request', function () {
    $request = slugRequest();

    expect(Field::keyValue('Meta')->disableEditingKeys()->schema(FieldContext::Update, $request)['disableEditingKeys'])->toBeTrue()
        ->and(Field::keyValue('Meta')->disableEditingKeys(fn (LatheRequest $r) => $r->path() === 'lathe/api/resources/slug-samples')
            ->schema(FieldContext::Update, $request)['disableEditingKeys'])->toBeTrue()
        ->and(Field::keyValue('Meta')->disableEditingKeys(fn (LatheRequest $r) => false)->schema(FieldContext::Update, $request)['disableEditingKeys'])->toBeFalse();
});

it('saves locked keys and reads the pairs back as an object', function () {
    $field = Field::keyValue('Meta')->disableEditingKeys();
    $id = LatheTest::resource('slug-samples')->store(['title' => 'x', 'meta' => [['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => '2']]])
        ->assertSuccessful()->json('id');

    expect(SlugSample::findOrFail($id)->meta)->toBe(['a' => '1', 'b' => '2'])
        ->and($field->resolve(SlugSample::findOrFail($id)))->toBe(['a' => '1', 'b' => '2']);
});

it('accepts the pairs as an object too', function () {
    $id = LatheTest::resource('slug-samples')->store(['title' => 'x', 'meta' => ['a' => '1']])->assertSuccessful()->json('id');

    expect(SlugSample::findOrFail($id)->meta)->toBe(['a' => '1']);
});

it('resolves an empty object to null', function () {
    expect(Field::keyValue('Meta')->resolve(new SlugSample(['meta' => []])))->toBeNull();
});

it('rejects a duplicate key, an empty key, a 256-character key and 201 pairs on the field', function (array $meta) {
    LatheTest::resource('slug-samples')->store(['title' => 'x', 'meta' => $meta])->assertStatus(422)->assertJsonValidationErrors(['meta']);
})->with([
    'duplicate' => [[['key' => 'a', 'value' => '1'], ['key' => 'a', 'value' => '2']]],
    'empty key' => [[['key' => '', 'value' => '1']]],
    'long key' => [[['key' => str_repeat('k', 256), 'value' => '1']]],
    'too many' => [array_map(fn (int $n) => ['key' => "k{$n}", 'value' => 'v'], range(1, 201))],
]);

it('accepts a 255-character key and 200 pairs', function () {
    $resource = LatheTest::resource('slug-samples');

    $resource->store(['title' => 'x', 'meta' => [['key' => str_repeat('k', 255), 'value' => '1']]])->assertSuccessful();
    $resource->store(['title' => 'x', 'meta' => array_map(fn (int $n) => ['key' => "k{$n}", 'value' => 'v'], range(1, 200))])->assertSuccessful();
});

it('takes the row and key limits from config', function () {
    config(['lathe.limits.key_value_rows' => 2, 'lathe.limits.key_length' => 3]);
    $resource = LatheTest::resource('slug-samples');

    $resource->store(['title' => 'x', 'meta' => [['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => '1'], ['key' => 'c', 'value' => '1']]])->assertStatus(422);
    $resource->store(['title' => 'x', 'meta' => [['key' => 'abcd', 'value' => '1']]])->assertStatus(422);
    $resource->store(['title' => 'x', 'meta' => [['key' => 'abc', 'value' => '1']]])->assertSuccessful();
});
