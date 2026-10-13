<?php

namespace Lathe\Tests\Feature\Layout;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Lathe\Enums\Icon;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Layout\Grid;
use Lathe\Layout\Section;
use Lathe\Layout\Tab;
use Lathe\Layout\TabGroup;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use LogicException;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

abstract class LayoutBaseOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }
}

/** Returns whatever items the test hands it. */
class LayoutItemsOrder extends LayoutBaseOrder
{
    /** @param list<mixed> $items */
    public function __construct(private array $items = [])
    {
        parent::__construct();
    }

    public function fields(LatheRequest $request): array
    {
        return $this->items;
    }
}

class LayoutFlatOrder extends LayoutBaseOrder
{
    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number'),
            Section::make('Money', [Field::make('Total Cents'), Field::make('Status')]),
            Grid::make(2)->schema([
                Section::make('Left', [Field::make('Placed At')]),
                Tab::group('Pages', [Tab::make('Notes', [Field::make('Created At')])]),
            ]),
        ];
    }
}

function layoutRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/layout-flat-orders'));
}

/**
 * @param  list<mixed>  $items
 * @return list<array<string, mixed>>
 */
function layoutJson(array $items, FieldContext $context = FieldContext::Detail, ?Order $model = null): array
{
    /** @var list<array<string, mixed>> */
    return json_decode((string) json_encode((new LayoutItemsOrder($items))->layoutFor($context, layoutRequest(), $model)), true);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(LayoutFlatOrder::class);
});

it('builds panel, grid and tab-group blocks, with a first Details panel carrying the toolbar', function () {
    $layout = layoutJson([
        Field::make('Number'),
        Section::make('Money', [Field::make('Total Cents')->columnSpan(2), Field::make('Status')->onlyOnIndex()])
            ->columns(2)->columnSpan(2)->limit(3)->borderless()->help('<b>ok</b>'),
        Grid::make(2)->schema([
            Section::make('Left', [Field::make('Placed At')])->collapsedByDefault(),
            Section::make('Right', [Field::make('Created At')])->hideHeading()->headingInCard(),
        ]),
        TabGroup::make('Pages', Tab::make('Notes', [Field::make('Updated At')])->icon(Icon::Bell)->badge('4')->badgeType('success')->columns(3)->borderless())
            ->activeTab(1)->vertical()->contained()->columnSpan(1),
    ]);

    expect($layout[0])->toBe([
        'type' => 'panel', 'attribute' => 'details', 'name' => 'Details', 'fields' => ['number'], 'toolbar' => true,
        'collapsible' => false, 'collapsed' => false, 'hideHeading' => false, 'headingInCard' => false,
        'help' => null, 'limit' => null, 'columns' => null, 'columnSpan' => null, 'borderless' => false,
    ])
        ->and($layout[1])->toBe([
            'type' => 'panel', 'attribute' => 'money', 'name' => 'Money', 'fields' => ['total_cents'], 'toolbar' => false,
            'collapsible' => false, 'collapsed' => false, 'hideHeading' => false, 'headingInCard' => false,
            'help' => ['__html' => '<b>ok</b>'], 'limit' => 3, 'columns' => 2, 'columnSpan' => 2, 'borderless' => true,
        ])
        ->and($layout[2]['type'])->toBe('grid')
        ->and($layout[2]['columns'])->toBe(2)
        ->and(array_column($layout[2]['items'], 'attribute'))->toBe(['left', 'right'])
        ->and($layout[2]['items'][0])->toMatchArray(['collapsible' => true, 'collapsed' => true, 'toolbar' => false])
        ->and($layout[2]['items'][1])->toMatchArray(['hideHeading' => true, 'headingInCard' => true, 'toolbar' => false])
        ->and($layout[3])->toBe([
            'type' => 'tab-group', 'attribute' => 'pages', 'label' => 'Pages', 'queryKey' => 'tab', 'activeTab' => 1,
            'vertical' => true, 'contained' => true, 'columnSpan' => 1,
            'tabs' => [[
                'attribute' => 'notes', 'label' => 'Notes', 'icon' => 'bell', 'badge' => '4', 'badgeType' => 'success',
                'columns' => 3, 'borderless' => true,
                'items' => [[
                    'type' => 'panel', 'attribute' => 'notes-fields', 'name' => 'Notes', 'fields' => ['updated_at'], 'toolbar' => false,
                    'collapsible' => false, 'collapsed' => false, 'hideHeading' => true, 'headingInCard' => false,
                    'help' => null, 'limit' => null, 'columns' => 3, 'columnSpan' => null, 'borderless' => false,
                ]],
            ]],
        ]);
});

it('gives the toolbar to the first panel when no field is loose', function () {
    $layout = layoutJson([Section::make('Money', [Field::make('Status')]), Section::make('Dates', [Field::make('Placed At')])]);

    expect(array_column($layout, 'toolbar'))->toBe([true, false]);
});

it('refuses a duplicate attribute and a span above the parent columns', function (Closure $items, string $exception, string $message) {
    $resource = new LayoutItemsOrder($items());

    expect(fn () => $resource->fieldsFor(FieldContext::Detail, layoutRequest()))->toThrow($exception, $message);
})->with([
    'section' => [fn () => [Section::make('Money'), Section::make('Cash')->withAttribute('money')], LogicException::class, 'Cash'],
    'tab' => [fn () => [TabGroup::make('G', Tab::make('A'), Tab::make('B')->withAttribute('a'))], LogicException::class, 'B'],
    'group' => [fn () => [TabGroup::make('G'), TabGroup::make('H')->withAttribute('g')], LogicException::class, 'H'],
    'span above grid' => [fn () => [Grid::make(2)->schema([Section::make('A')->columnSpan(3)])], InvalidArgumentException::class, 'spans 3'],
    'field span above section' => [fn () => [Section::make('A', [Field::make('X')->columnSpan(3)])->columns(2)], InvalidArgumentException::class, 'spans 3'],
]);

it('throws at once for a column count outside 1 to 6 and an unknown badge type', function () {
    expect(fn () => Section::make('A')->columns(7))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Grid::make(7))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Tab::make('A')->columns(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Tab::make('A')->badgeType('loud'))->toThrow(InvalidArgumentException::class);
});

it('passes Section help through the sanitizer', function (string $payload, string $clean) {
    $layout = layoutJson([Section::make('Help', [Field::make('Number')])->help($payload)]);

    expect($layout[0]['help'])->toBe(['__html' => $clean]);
})->with('xss_payloads');

it('names the query key of each tab group', function () {
    $layout = layoutJson([
        TabGroup::make('First', Tab::make('A', [Field::make('Number')])),
        TabGroup::make('Second', Tab::make('B', [Field::make('Status')])),
        TabGroup::make('Third', Tab::make('C', [Field::make('Total Cents')]))->persistTab('t'),
    ]);

    expect(array_column($layout, 'queryKey'))->toBe(['tab', 'tab_second', 't']);
});

it('leaves out a tab that canSee refuses and runs a badge closure on detail only', function () {
    $items = fn () => [
        TabGroup::make('Pages',
            Tab::make('Hidden', [Field::make('Number')])->canSee(fn (LatheRequest $request) => false),
            Tab::make('Shown', [Field::make('Status')])->badge(fn (?Order $record, LatheRequest $request) => 'n'.$record?->getKey()),
        ),
    ];
    $order = Order::findOrFail(12);

    $detail = layoutJson($items(), FieldContext::Detail, $order);
    $update = layoutJson($items(), FieldContext::Update, $order);

    expect(array_column($detail[0]['tabs'], 'label'))->toBe(['Shown'])
        ->and($detail[0]['tabs'][0]['badge'])->toBe('n12')
        ->and($update[0]['tabs'][0]['badge'])->toBeNull();
});

it('drops every panel, tab and grid without a form field on a form', function () {
    $items = fn () => [
        Field::make('Number'),
        Section::make('Read only', [Field::make('Created At')->onlyOnDetail()]),
        Grid::make(2)->schema([Section::make('Also read only', [Field::make('Updated At')->onlyOnDetail()])]),
        TabGroup::make('Pages', Tab::make('Gone', [Field::make('Placed At')->onlyOnDetail()]), Tab::make('Kept', [Field::make('Status')])),
    ];

    $create = layoutJson($items(), FieldContext::Create);
    $detail = layoutJson($items(), FieldContext::Detail);

    expect(array_column($create, 'type'))->toBe(['panel', 'tab-group'])
        ->and(array_column($create[1]['tabs'], 'label'))->toBe(['Kept'])
        ->and(array_column($detail, 'type'))->toBe(['panel', 'panel', 'grid', 'tab-group']);
});

it('flattens layout containers into index columns in declaration order', function () {
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $columns = $this->getJson('/lathe/api/resources/layout-flat-orders')->assertOk()->json('table.columns');

    expect(array_column($columns, 'key'))->toBe(['number', 'total_cents', 'status', 'placed_at', 'created_at']);
});
