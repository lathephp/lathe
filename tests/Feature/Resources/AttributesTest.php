<?php

use Lathe\Attributes\Resource as Attr;
use Lathe\Enums\ClickAction;
use Lathe\Enums\PaginationType;
use Lathe\Enums\TableStyle;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\LikeSearch;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

#[Attr\Model(Order::class)]
#[Attr\PerPage([25])]
#[Attr\SavedTabs]
class MixedAttributeOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->perPage([50, 100])->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

#[Attr\Model(Order::class)]
#[Attr\GloballySearchable]
#[Attr\Polling(10)]
class OverriddenConfigureOrder extends Resource
{
    // Does not call parent::configure(), which once dropped the attributes.
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

/**
 * Everything a definition exposes, so two definitions compare as a whole.
 *
 * @return array<string, mixed>
 */
function definitionSnapshot(ResourceDefinition $definition): array
{
    return [
        ...$definition->options(),
        'model' => $definition->getModel(),
        'title' => $definition->getTitle(),
        'subtitle' => $definition->getSubtitle(),
        'with' => $definition->getWith(),
        'group' => $definition->getGroup(),
        'icon' => $definition->getIcon(),
        'navigationLabel' => $definition->getNavigationLabel(),
        'displayInNavigation' => $definition->shouldDisplayInNavigation(),
        'searchable' => array_map(fn ($column) => is_string($column) ? $column : $column::class, $definition->getSearchable()),
    ];
}

it('gives an attribute the same definition as its fluent call', function (string $attribute, Closure $fluent) {
    $class = 'AttributeFixture'.bin2hex(random_bytes(6));
    eval("#[{$attribute}] class {$class} {}");

    expect(definitionSnapshot(ResourceDefinition::fromAttributes($class)))
        ->toBe(definitionSnapshot($fluent(new ResourceDefinition)))
        ->not->toBe(definitionSnapshot(new ResourceDefinition));
})->with([
    'Model' => ['\Lathe\Attributes\Resource\Model(\Workbench\App\Models\Customer::class)', fn ($d) => $d->model(Customer::class)],
    'Title' => ['\Lathe\Attributes\Resource\Title("name")', fn ($d) => $d->title('name')],
    'Subtitle' => ['\Lathe\Attributes\Resource\Subtitle("email")', fn ($d) => $d->subtitle('email')],
    'Search' => ['\Lathe\Attributes\Resource\Search(["name", new \Lathe\Search\LikeSearch("email", true)])', fn ($d) => $d->searchable(['name', new LikeSearch('email', true)])],
    'EagerLoad' => ['\Lathe\Attributes\Resource\EagerLoad(["customer"])', fn ($d) => $d->with(['customer'])],
    'PerPage' => ['\Lathe\Attributes\Resource\PerPage([10, 20])', fn ($d) => $d->perPage([10, 20])],
    'MaxPrimaryKeySize' => ['\Lathe\Attributes\Resource\MaxPrimaryKeySize(99)', fn ($d) => $d->maxPrimaryKeySize(99)],
    'SoftDeletes' => ['\Lathe\Attributes\Resource\SoftDeletes', fn ($d) => $d->softDeletes()],
    'SavedTabs' => ['\Lathe\Attributes\Resource\SavedTabs', fn ($d) => $d->savedTabs()],
    'TrafficCop' => ['\Lathe\Attributes\Resource\TrafficCop(false)', fn ($d) => $d->trafficCop(false)],
    'GloballySearchable' => ['\Lathe\Attributes\Resource\GloballySearchable', fn ($d) => $d->globallySearchable()],
    'ScoutSearchResults' => ['\Lathe\Attributes\Resource\ScoutSearchResults(50)', fn ($d) => $d->scoutSearchResults(50)],
    'UseScout' => ['\Lathe\Attributes\Resource\UseScout(false)', fn ($d) => $d->useScout(false)],
    'Polling' => ['\Lathe\Attributes\Resource\Polling(10)', fn ($d) => $d->polling(10)],
    'ShowPollingToggle' => ['\Lathe\Attributes\Resource\ShowPollingToggle', fn ($d) => $d->showPollingToggle()],
    'Debounce' => ['\Lathe\Attributes\Resource\Debounce(0.25)', fn ($d) => $d->debounce(0.25)],
    'PaginationType' => ['\Lathe\Attributes\Resource\PaginationType(\Lathe\Enums\PaginationType::LoadMore)', fn ($d) => $d->paginationType(PaginationType::LoadMore)],
    'ClickAction' => ['\Lathe\Attributes\Resource\ClickAction(\Lathe\Enums\ClickAction::Select)', fn ($d) => $d->clickAction(ClickAction::Select)],
    'TableStyle' => ['\Lathe\Attributes\Resource\TableStyle(\Lathe\Enums\TableStyle::Tight)', fn ($d) => $d->tableStyle(TableStyle::Tight)],
    'ShowColumnBorders' => ['\Lathe\Attributes\Resource\ShowColumnBorders', fn ($d) => $d->showColumnBorders()],
    'Borderless' => ['\Lathe\Attributes\Resource\Borderless', fn ($d) => $d->borderless()],
    'GlobalSearchResults' => ['\Lathe\Attributes\Resource\GlobalSearchResults(3)', fn ($d) => $d->globalSearchResults(3)],
    'Group' => ['\Lathe\Attributes\Resource\Group("Sales")', fn ($d) => $d->group('Sales')],
    'Icon' => ['\Lathe\Attributes\Resource\Icon("ShoppingCart")', fn ($d) => $d->icon('ShoppingCart')],
    'NavigationLabel' => ['\Lathe\Attributes\Resource\NavigationLabel("Sales orders")', fn ($d) => $d->navigationLabel('Sales orders')],
    'DisplayInNavigation' => ['\Lathe\Attributes\Resource\DisplayInNavigation(false)', fn ($d) => $d->displayInNavigation(false)],
]);

it('lets a fluent call beat the attribute', function () {
    $definition = MixedAttributeOrder::make()->definition();

    expect($definition->getPerPage())->toBe([50, 100])
        ->and($definition->options()['perPage'])->toBe([50, 100])
        ->and($definition->usesSavedTabs())->toBeTrue()
        ->and($definition->getModel())->toBe(Order::class);
});

test('attributes_apply_when_configure_is_overridden', function () {
    $definition = OverriddenConfigureOrder::make()->definition();

    expect($definition->isGloballySearchable())->toBeTrue()
        ->and($definition->getPolling())->toBe(10)
        ->and($definition->getModel())->toBe(Order::class);
})->group('regression');

it('builds the definition once and freezes it', function () {
    $definition = MixedAttributeOrder::make()->definition();

    expect(MixedAttributeOrder::make()->definition())->toBe($definition)
        ->and(fn () => $definition->polling(5))->toThrow(LogicException::class);
});
