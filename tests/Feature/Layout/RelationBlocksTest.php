<?php

namespace Lathe\Tests\Feature\Layout;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Layout\Section;
use Lathe\Layout\SectionPresenter;
use Lathe\Layout\Tab;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Returns whatever items the test hands it. */
class RelationBlocksOrder extends Resource
{
    /** @var list<mixed> */
    public static array $items = [];

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return self::$items;
    }
}

/**
 * @param  list<mixed>  $items
 * @return list<array<string, mixed>>
 */
function relationLayout(array $items, FieldContext $context = FieldContext::Detail): array
{
    $request = LatheRequest::createFrom(Request::create('/lathe/api/resources/relation-blocks-orders'));

    RelationBlocksOrder::$items = $items;

    /** @var list<array<string, mixed>> */
    return json_decode((string) json_encode((new RelationBlocksOrder)->layoutFor($context, $request, null)), true);
}

/** @return list<mixed> */
function relationItems(): array
{
    return [
        Section::make('Order', [Field::text('Number')]),
        Field::hasMany('Items'),
        Tab::group(null, [Tab::make('Comments', [Field::morphMany('Comments')])]),
    ];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(RelationBlocksOrder::class);
});

it('adds a relation-field block after the section and inside the tab on detail', function () {
    $layout = relationLayout(relationItems());

    expect(array_column($layout, 'type'))->toBe(['panel', 'relation-field', 'tab-group'])
        ->and($layout[0]['name'])->toBe('Order')
        ->and($layout[0]['fields'])->toBe(['number'])
        ->and($layout[1]['key'])->toBe('items')
        ->and($layout[1]['field'])->toMatchArray(['kind' => 'hasMany', 'relationship' => 'items'])
        ->and($layout[2]['tabs'][0]['items'][0]['type'])->toBe('relation-field')
        ->and($layout[2]['tabs'][0]['items'][0]['key'])->toBe('comments');
});

it('gives hasOne a has-one block and hasOneOfMany none', function () {
    $layout = relationLayout([Section::make('Order', [Field::text('Number')]), Field::hasOne('Warranty'), Field::hasOneOfMany('Latest Order')]);

    expect(array_column($layout, 'type'))->toBe(['panel', 'panel', 'has-one-warranty'])
        ->and($layout[0]['fields'])->toBe(['latestOrder'])
        ->and($layout[2]['key'])->toBe('warranty')
        ->and($layout[2]['field']['kind'])->toBe('hasOne');
});

it('drops the relation blocks and the emptied tab on forms', function (FieldContext $context) {
    $layout = relationLayout(relationItems(), $context);

    expect(array_column($layout, 'type'))->toBe(['panel']);
})->with([FieldContext::Create, FieldContext::Update]);

it('filters relation blocks out of a hand-built layout', function () {
    $panel = ['type' => 'panel', 'fields' => ['number']];
    $layout = [$panel, ['type' => 'relation-field', 'key' => 'items'], ['type' => 'has-one-warranty', 'key' => 'warranty']];

    expect(SectionPresenter::filterLayoutForForm($layout))->toBe([$panel]);
});
