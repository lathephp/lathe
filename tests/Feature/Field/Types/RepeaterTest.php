<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Repeater\Repeatable;
use Lathe\Field\RepeaterField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class RepeaterRecord extends Model
{
    protected $table = 'repeater_things';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return array_fill_keys(['specifications', 'tags', 'blocks', 'tree', 'rows', 'items', 'lines', 'logged'], 'array');
    }
}

class RepeaterLog
{
    /** @var list<string> */
    public array $calls = [];

    public int $ruleRuns = 0;
}

class RepeaterThing extends Resource
{
    public static ?RepeaterLog $log = null;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(RepeaterRecord::class)->title('id')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return array_values(repeaterFields());
    }
}

/**
 * The fixture's fields, keyed by attribute, so a test reads one through the field API.
 *
 * @return array<string, RepeaterField>
 */
function repeaterFields(): array
{
    return [
        'specifications' => Field::repeater('Specifications')->fields([Field::text('Name')->required(), Field::text('Value')]),
        'tags' => Field::repeater('Tags')->simple(Field::text('Tag')->rules('max:5')),
        'blocks' => Field::repeater('Blocks')->repeatables([
            Repeatable::make('Text Block', [Field::text('Body')->required()]),
            Repeatable::make('Image', [Field::text('Url')->required()]),
        ]),
        'tree' => Field::repeater('Tree')->fields([
            Field::text('Name'),
            Field::repeater('Children')->fields([
                Field::text('Name'),
                Field::repeater('Leaves')->fields([Field::text('Name')->required()]),
            ]),
        ]),
        'rows' => Field::repeater('Rows')->fields([Field::text('Title')->translatable(['en', 'fr'])->required()]),
        'items' => Field::repeater('Items')->fields([Field::text('Sku')])->min(1)->max(2)->distinct('sku'),
        'lines' => Field::repeater('Lines')->fields([Field::text('Note')->rules(function (string $attribute, mixed $value, \Closure $fail) {
            if (RepeaterThing::$log !== null) {
                RepeaterThing::$log->ruleRuns++;
            }
        })]),
        'logged' => Field::repeater('Logged')->fields([Field::text('Name')])
            ->beforeFill(function (array $rows) {
                if (RepeaterThing::$log !== null) {
                    RepeaterThing::$log->calls[] = 'fill';
                }

                return [...$rows, ['name' => 'added on load']];
            })
            ->beforeSave(function (array $rows) {
                if (RepeaterThing::$log !== null) {
                    RepeaterThing::$log->calls[] = 'save';
                }

                return array_map(fn (array $row) => ['name' => strtoupper((string) $row['name'])], $rows);
            }),
    ];
}

function repeaterRecord(): RepeaterRecord
{
    return RepeaterRecord::query()->create([]);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('repeater_things');
    Schema::create('repeater_things', function (Blueprint $table) {
        $table->id();

        foreach (['specifications', 'tags', 'blocks', 'tree', 'rows', 'items', 'lines', 'logged'] as $column) {
            $table->json($column)->nullable();
        }
    });
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(RepeaterThing::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    RepeaterThing::$log = null;
});

it('builds a repeater-field shown on detail and forms and hidden from index', function () {
    $field = Field::repeater('Specifications');

    expect($field)->toBeInstanceOf(RepeaterField::class)
        ->and($field->getComponent())->toBe('repeater-field')
        ->and($field->attribute())->toBe('specifications')
        ->and(Field::repeater('Specs', 'spec_rows')->attribute())->toBe('spec_rows')
        ->and($field->isShownOn(FieldContext::Index))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Detail))->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeTrue()
        ->and($field->isShownOn(FieldContext::Update))->toBeTrue();
});

it('saves, reorders and loads fields rows as an array', function () {
    $record = repeaterRecord();
    $rows = [['name' => 'Color', 'value' => 'Red'], ['name' => 'Size', 'value' => 'L']];

    LatheTest::resource('repeater-things')->update($record->id, ['specifications' => $rows])->assertOk();

    expect($record->refresh()->specifications)->toBe($rows)
        ->and(repeaterFields()['specifications']->resolve($record))->toBe($rows);

    LatheTest::resource('repeater-things')->update($record->id, ['specifications' => array_reverse($rows)])->assertOk();

    expect($record->refresh()->specifications)->toBe([['name' => 'Size', 'value' => 'L'], ['name' => 'Color', 'value' => 'Red']]);
});

it('stores a simple repeater as a flat array', function () {
    $record = repeaterRecord();

    LatheTest::resource('repeater-things')->update($record->id, ['tags' => ['a', 'b']])->assertOk();

    expect($record->refresh()->getRawOriginal('tags'))->toBe('["a","b"]')
        ->and(repeaterFields()['tags']->resolve($record))->toBe(['a', 'b']);

    LatheTest::resource('repeater-things')->update($record->id, ['tags' => ['a', 'toolong']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags.1');
});

it('stores a polymorphic row with its kebab-case block type and that block\'s fields', function () {
    $record = repeaterRecord();
    $rows = [['type' => 'text-block', 'body' => 'Hello'], ['type' => 'image', 'url' => '/a.png']];

    LatheTest::resource('repeater-things')->update($record->id, ['blocks' => [
        ['type' => 'text-block', 'body' => 'Hello', 'url' => 'dropped'],
        ['type' => 'image', 'url' => '/a.png'],
    ]])->assertOk();

    expect($record->refresh()->blocks)->toBe($rows)
        ->and(repeaterFields()['blocks']->resolve($record))->toBe($rows)
        ->and(Repeatable::make('Text Block')->key())->toBe('text-block');
});

it('checks a polymorphic row against its own block only and its type against the block keys', function () {
    $record = repeaterRecord();

    $response = LatheTest::resource('repeater-things')->update($record->id, ['blocks' => [
        ['type' => 'image'],
        ['type' => 'video'],
        ['body' => 'no type'],
    ]])->assertUnprocessable();

    expect(array_keys($response->json('errors')))->toEqualCanonicalizing(['blocks.0.url', 'blocks.1.type', 'blocks.2.type']);
});

it('saves and loads a nested JSON repeater three levels deep', function () {
    $record = repeaterRecord();
    $tree = [['name' => 'A', 'children' => [['name' => 'B', 'leaves' => [['name' => 'C']]]]]];

    LatheTest::resource('repeater-things')->update($record->id, ['tree' => $tree])->assertOk();

    expect($record->refresh()->tree)->toBe($tree)
        ->and(repeaterFields()['tree']->resolve($record))->toBe($tree);

    LatheTest::resource('repeater-things')->update($record->id, ['tree' => [['name' => 'A', 'children' => [['name' => 'B', 'leaves' => [['name' => '']]]]]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tree.0.children.0.leaves.0.name');
});

it('keys sub-field errors by row, translatable locales included', function () {
    $record = repeaterRecord();

    LatheTest::resource('repeater-things')->update($record->id, ['specifications' => [['name' => 'Ok'], ['value' => 'x']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('specifications.1.name')
        ->assertJsonMissingValidationErrors('specifications.0.name');

    LatheTest::resource('repeater-things')->update($record->id, ['rows' => [['title' => ['fr' => 'Bonjour']]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rows.0.title.en');

    LatheTest::resource('repeater-things')->update($record->id, ['rows' => [['title' => ['en' => 'Hi', 'fr' => 'Salut']]]])->assertOk();

    expect($record->refresh()->rows)->toBe([['title' => ['en' => 'Hi', 'fr' => 'Salut']]]);
});

it('enforces min, max and distinct with 422', function () {
    $record = repeaterRecord();

    LatheTest::resource('repeater-things')->update($record->id, ['items' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    LatheTest::resource('repeater-things')->update($record->id, ['items' => [['sku' => 'a'], ['sku' => 'b'], ['sku' => 'c']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    LatheTest::resource('repeater-things')->update($record->id, ['items' => [['sku' => 'a'], ['sku' => 'a']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.1.sku');
    LatheTest::resource('repeater-things')->update($record->id, ['items' => [['sku' => 'a'], ['sku' => 'b']]])->assertOk();

    expect($record->refresh()->items)->toBe([['sku' => 'a'], ['sku' => 'b']]);
});

it('refuses more rows than the configured cap before any rule runs', function () {
    $record = repeaterRecord();
    RepeaterThing::$log = new RepeaterLog;

    LatheTest::resource('repeater-things')->update($record->id, ['lines' => array_fill(0, 201, ['note' => 'x'])])
        ->assertUnprocessable()->assertJsonValidationErrors('lines');

    expect(RepeaterThing::$log->ruleRuns)->toBe(0);

    LatheTest::resource('repeater-things')->update($record->id, ['lines' => array_fill(0, 2, ['note' => 'x'])])->assertOk();

    expect(RepeaterThing::$log->ruleRuns)->toBe(2);

    config(['lathe.limits.repeater_rows' => 3]);

    LatheTest::resource('repeater-things')->update($record->id, ['lines' => array_fill(0, 4, ['note' => 'x'])])
        ->assertUnprocessable()->assertJsonValidationErrors('lines');
});

it('runs beforeFill once on load and beforeSave once before persist', function () {
    $record = repeaterRecord();
    RepeaterThing::$log = new RepeaterLog;

    LatheTest::resource('repeater-things')->update($record->id, ['logged' => [['name' => 'a']]])->assertOk();

    expect($record->refresh()->logged)->toBe([['name' => 'A']])
        ->and(RepeaterThing::$log->calls)->toBe(['save']);

    expect(repeaterFields()['logged']->resolve($record))->toBe([['name' => 'A'], ['name' => 'added on load']])
        ->and(RepeaterThing::$log->calls)->toBe(['save', 'fill']);

    $form = LatheTest::resource('repeater-things')->updateFields($record->id)->assertOk();

    expect(collect($form->json('fields'))->firstWhere('attribute', 'logged')['value'])->toBe([['name' => 'A'], ['name' => 'added on load']])
        ->and(RepeaterThing::$log->calls)->toBe(['save', 'fill', 'fill']);
});

it('sends its mode, sub-fields and options in the payload', function () {
    $payload = Field::repeater('Specifications')->fields([Field::text('Name')])
        ->collapsed()->cloneable()->itemNumbers()->confirmRemoval()
        ->sortable(false)->addable(false)->deletable(false)
        ->grid(3)->addButtonText('Add spec')->compact()
        ->rowLabel('name', 'Spec #{{index}}')
        ->jsonSerialize();

    expect($payload['mode'])->toBe('fields')
        ->and(array_column($payload['fields'], 'attribute'))->toBe(['name'])
        ->and(array_intersect_key($payload, array_flip([
            'reorderable', 'addable', 'deletable', 'collapsible', 'collapsed', 'cloneable', 'itemNumbers',
            'confirmRemoval', 'grid', 'addButtonText', 'compact', 'rowLabel', 'sortable',
        ])))->toEqual([
            'reorderable' => false, 'addable' => false, 'deletable' => false, 'collapsible' => true, 'collapsed' => true,
            'cloneable' => true, 'itemNumbers' => true, 'confirmRemoval' => true, 'grid' => 3, 'addButtonText' => 'Add spec',
            'compact' => true, 'rowLabel' => ['field' => 'name', 'fallback' => 'Spec #{{index}}'], 'sortable' => false,
        ]);

    $defaults = Field::repeater('Specifications')->fields([Field::text('Name')])->jsonSerialize();

    expect([$defaults['reorderable'], $defaults['addable'], $defaults['deletable'], $defaults['collapsible'], $defaults['cloneable'], $defaults['rowLabel']])
        ->toBe([true, true, true, false, false, null]);

    $blocks = Field::repeater('Blocks')->repeatables([Repeatable::make('Text Block', fn () => [Field::text('Body')])->icon('type')->confirmBeforeRemoval()])->jsonSerialize();

    expect($blocks['mode'])->toBe('repeatables')
        ->and($blocks['blocks'][0])->toMatchArray(['key' => 'text-block', 'label' => 'Text Block', 'icon' => 'type', 'confirmRemoval' => true])
        ->and(array_column($blocks['blocks'][0]['fields'], 'attribute'))->toBe(['body'])
        ->and(Field::repeater('Tags')->simple(Field::text('Tag'))->jsonSerialize()['mode'])->toBe('simple');

    expect(fn () => Field::repeater('Specifications')->grid(7))->toThrow(InvalidArgumentException::class, 'Specifications');
});

it('pre-adds defaultRows empty rows on the create form only', function () {
    $field = Field::repeater('Specifications')->fields([Field::text('Name'), Field::text('Value')])->defaultRows(2);
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/repeater-things/creation-fields'));

    expect($field->resolveDefaultValue($request))->toBe([['name' => null, 'value' => null], ['name' => null, 'value' => null]])
        ->and($field->resolve(repeaterRecord()))->toBe([]);
});

it('computes a Closure row label on the server at load', function () {
    $record = repeaterRecord();
    $record->update(['specifications' => [['name' => 'Color', 'value' => 'Red'], ['name' => 'Size', 'value' => 'L']]]);

    $field = Field::repeater('Specifications')->fields([Field::text('Name'), Field::text('Value')])
        ->rowLabel(fn (array $row, int $index) => "{$index}: {$row['name']}");

    expect($field->resolve($record))->toBe([
        ['name' => 'Color', 'value' => 'Red', '_label' => '0: Color'],
        ['name' => 'Size', 'value' => 'L', '_label' => '1: Size'],
    ])->and($field->jsonSerialize()['rowLabel'])->toBe(['field' => null, 'fallback' => null]);
});
