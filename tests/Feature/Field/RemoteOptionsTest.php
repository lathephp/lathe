<?php

namespace Lathe\Tests\Feature\Field;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixPolicy;
use Lathe\Tests\Fixtures\Models\Matrix\MatrixSchema;
use Lathe\Tests\Fixtures\Models\Matrix\MxParent;
use Lathe\Tests\Fixtures\Models\Matrix\MxTag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class RemoteOptionsSetup
{
    public static ?int $inlineUpTo = null;
}

class RemoteOptionsParents extends Resource
{
    public static ?string $policy = MatrixPolicy::class;

    public static function uriKey(): string
    {
        return 'remote-parents';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MxParent::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        $tags = fn () => MxTag::query();

        return [
            Field::text('Name'),
            Field::multiSelect('Roles')->remoteOptions($tags, 'name', RemoteOptionsSetup::$inlineUpTo),
            Field::select('Role')->remoteOptions($tags, 'name', RemoteOptionsSetup::$inlineUpTo),
            Field::multiSelect('Secret')->remoteOptions($tags)->canSee(fn () => false),
        ];
    }
}

/**
 * @param  list<string>  $names
 */
function remoteTags(array $names): void
{
    DB::table('mx_tags')->insert(array_map(fn (string $name) => ['name' => $name], $names));
}

/**
 * @return list<string>
 */
function remoteNames(string $prefix, int $count): array
{
    return array_map(fn (int $i) => sprintf('%s %02d', $prefix, $i), range(1, $count));
}

/**
 * @return array<string, mixed>
 */
function remoteField(array $form, string $key): array
{
    return collect($form['fields'])->firstWhere('key', $key);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    MatrixSchema::create();
    MatrixPolicy::$denied = [];
    RemoteOptionsSetup::$inlineUpTo = null;
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(RemoteOptionsParents::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('builds the create form with no query on the option table and marks the fields remote', function () {
    remoteTags(remoteNames('item', 30));

    $tables = [];
    DB::listen(function ($query) use (&$tables) {
        if (str_contains($query->sql, 'mx_tags')) {
            $tables[] = $query->sql;
        }
    });

    $form = $this->getJson('/lathe/api/resources/remote-parents/creation-fields')->assertOk()->json();

    expect($tables)->toBe([])
        ->and(remoteField($form, 'roles'))->toMatchArray(['remote' => true, 'options' => []])
        ->and(remoteField($form, 'role'))->toMatchArray(['remote' => true, 'options' => []]);
});

it('searches the column for the term, ordered by it, 25 at most', function (string $field) {
    remoteTags([...array_reverse(remoteNames('record', 30)), ...remoteNames('item', 30)]);

    $json = $this->getJson("/lathe/api/resources/remote-parents/associatable/{$field}?q=ord")->assertOk()->json();

    expect($json)->toBe([
        'items' => array_map(fn (string $name) => ['value' => $name, 'display' => $name], remoteNames('record', 25)),
        'has_more' => true,
    ]);
})->with(['multi-select' => 'roles', 'select' => 'role']);

it('says there is no more when fewer than a page match', function (string $field) {
    remoteTags(['order 3', 'item 01', 'record 2', 'item 02', 'cordial']);

    $json = $this->getJson("/lathe/api/resources/remote-parents/associatable/{$field}?q=ORD")->assertOk()->json();

    expect($json)->toBe([
        'items' => [
            ['value' => 'cordial', 'display' => 'cordial'],
            ['value' => 'order 3', 'display' => 'order 3'],
            ['value' => 'record 2', 'display' => 'record 2'],
        ],
        'has_more' => false,
    ]);
})->with(['multi-select' => 'roles', 'select' => 'role']);

it('matches percent and underscore in the term as themselves', function () {
    remoteTags(['50% off', '500 off', 'a_b', 'axb']);

    expect($this->getJson('/lathe/api/resources/remote-parents/associatable/roles?q=0%25')->json('items'))->toBe([['value' => '50% off', 'display' => '50% off']])
        ->and($this->getJson('/lathe/api/resources/remote-parents/associatable/roles?q=a_')->json('items'))->toBe([['value' => 'a_b', 'display' => 'a_b']]);
});

it('answers a saved value with its one row, and 404 for an unknown one', function () {
    remoteTags(remoteNames('item', 30));

    expect($this->getJson('/lathe/api/resources/remote-parents/associatable/role?current=item%2007')->assertOk()->json())
        ->toBe(['items' => [['value' => 'item 07', 'display' => 'item 07']], 'has_more' => false]);

    $this->getJson('/lathe/api/resources/remote-parents/associatable/role?current=item%2099')->assertNotFound();
});

it('serves the saved record behind view', function () {
    remoteTags(['record 1', 'item 1']);
    $parent = MxParent::query()->create(['name' => 'Alpha']);

    expect($this->getJson("/lathe/api/resources/remote-parents/{$parent->id}/roles/associatable?q=ord")->assertOk()->json())
        ->toBe(['items' => [['value' => 'record 1', 'display' => 'record 1']], 'has_more' => false]);

    MatrixPolicy::$denied = ['view'];

    $this->getJson("/lathe/api/resources/remote-parents/{$parent->id}/roles/associatable?q=ord")->assertForbidden();
});

it('answers 404 for an unknown field or one without remote options, 403 for a hidden one', function () {
    $this->getJson('/lathe/api/resources/remote-parents/associatable/nothing')->assertNotFound();
    $this->getJson('/lathe/api/resources/remote-parents/associatable/name')->assertNotFound();
    $this->getJson('/lathe/api/resources/remote-parents/associatable/secret')->assertForbidden();
});

it('inlines the options when the source holds no more than inlineUpTo rows', function () {
    RemoteOptionsSetup::$inlineUpTo = 100;
    remoteTags(array_reverse(remoteNames('item', 100)));

    $roles = remoteField($this->getJson('/lathe/api/resources/remote-parents/creation-fields')->assertOk()->json(), 'roles');

    expect($roles)->not->toHaveKey('remote')
        ->and($roles['options'])->toHaveCount(100)
        ->and($roles['options'][0])->toBe(['value' => 'item 01', 'label' => 'item 01'])
        ->and($roles['options'][99])->toBe(['value' => 'item 99', 'label' => 'item 99']);
});

it('stays remote when the source holds more than inlineUpTo rows', function () {
    RemoteOptionsSetup::$inlineUpTo = 100;
    remoteTags(remoteNames('item', 101));

    $form = $this->getJson('/lathe/api/resources/remote-parents/creation-fields')->assertOk()->json();

    expect(remoteField($form, 'roles'))->toMatchArray(['remote' => true, 'options' => []])
        ->and(remoteField($form, 'role'))->toMatchArray(['remote' => true, 'options' => []]);
});
