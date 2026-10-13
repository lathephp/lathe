<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Enums\ClickAction;
use Lathe\Enums\PaginationType;
use Lathe\Enums\TableStyle;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\CountStrategy;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Invoice as InvoiceModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class DefinitionPlainResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(InvoiceModel::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class DefinitionTunedResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(InvoiceModel::class)
            ->perPage([10, 30])
            ->maxPrimaryKeySize(99999)
            ->savedTabs()
            ->trafficCop(false)
            ->scoutSearchResults(50)
            ->useScout(false)
            ->polling(10)
            ->showPollingToggle()
            ->debounce(0.25)
            ->paginationType(PaginationType::LoadMore)
            ->clickAction(ClickAction::Preview)
            ->tableStyle(TableStyle::Tight)
            ->showColumnBorders()
            ->borderless()
            ->globalSearchResults(3)
            ->bulkInlineMax(40)
            ->countStrategy(CountStrategy::Estimated, 2000)
            ->pickerResults(15)
            ->searchTermChars(60)
            ->searchTermWords(4)
            ->filterRemoteThreshold(30)
            ->idListMax(200)
            ->defaultGroup('status')
            ->softDeletes(false)
            ->globallySearchable()
            ->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class DefinitionZeroCapResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(InvoiceModel::class)->countStrategy(CountStrategy::Exact, 0);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

class DefinitionZeroPollingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(InvoiceModel::class)->polling(0);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

it('fills every unset option from the config defaults', function () {
    expect(DefinitionPlainResource::make()->definition()->options())->toBe([
        'perPage' => [25, 50, 100],
        'maxPrimaryKeySize' => PHP_INT_MAX,
        'canonical' => true,
        'softDeletes' => false,
        'savedTabs' => false,
        'trafficCop' => true,
        'globallySearchable' => false,
        'scoutSearchResults' => 200,
        'useScout' => null,
        'polling' => null,
        'showPollingToggle' => false,
        'debounce' => 0.5,
        'paginationType' => 'links',
        'clickAction' => 'detail',
        'tableStyle' => 'default',
        'showColumnBorders' => false,
        'borderless' => false,
        'globalSearchResults' => 5,
        'bulkInlineMax' => 500,
        'countStrategy' => 'capped',
        'countCap' => 10000,
        'pickerResults' => 25,
        'searchTermChars' => 100,
        'searchTermWords' => 8,
        'filterRemoteThreshold' => 100,
        'idListMax' => 1000,
        'defaultGroup' => null,
    ]);
});

it('reads the config when asked, not when the definition is built', function () {
    $definition = DefinitionPlainResource::make()->definition();

    config([
        'lathe.global_search.results' => 2,
        'lathe.pagination.type' => 'simple',
        'lathe.pagination.per_page_options' => [10, 20],
        'lathe.limits.count_cap' => 500,
    ]);

    expect($definition->options())
        ->toMatchArray(['globalSearchResults' => 2, 'paginationType' => 'simple', 'perPage' => [10, 20], 'countCap' => 500])
        ->and($definition->getGlobalSearchResults())->toBe(2);
});

it('sends each fluent setting in the options', function () {
    expect(DefinitionTunedResource::make()->definition()->options())->toBe([
        'perPage' => [10, 30],
        'maxPrimaryKeySize' => 99999,
        'canonical' => false,
        'softDeletes' => false,
        'savedTabs' => true,
        'trafficCop' => false,
        'globallySearchable' => true,
        'scoutSearchResults' => 50,
        'useScout' => false,
        'polling' => 10,
        'showPollingToggle' => true,
        'debounce' => 0.25,
        'paginationType' => 'load-more',
        'clickAction' => 'preview',
        'tableStyle' => 'tight',
        'showColumnBorders' => true,
        'borderless' => true,
        'globalSearchResults' => 3,
        'bulkInlineMax' => 40,
        'countStrategy' => 'estimated',
        'countCap' => 2000,
        'pickerResults' => 15,
        'searchTermChars' => 60,
        'searchTermWords' => 4,
        'filterRemoteThreshold' => 30,
        'idListMax' => 200,
        'defaultGroup' => 'status',
    ]);
});

it('keeps the config cap when countStrategy() names no cap', function () {
    $definition = (new ResourceDefinition)->countStrategy(CountStrategy::None);

    expect($definition->getCountStrategy())->toBe(CountStrategy::None)
        ->and($definition->getCountCap())->toBe(10000);
});

it('refuses a setter after the definition is built', function () {
    $definition = DefinitionPlainResource::make()->definition();

    expect(fn () => $definition->polling(5))
        ->toThrow(LogicException::class, 'cannot change after it is built');
});

it('fails on a count cap below 1', function () {
    expect(fn () => DefinitionZeroCapResource::make()->definition())
        ->toThrow(InvalidConfigurationException::class, 'DefinitionZeroCapResource: countStrategy: the cap must be at least 1; got 0.');
});

it('fails on a polling interval below 1', function () {
    expect(fn () => DefinitionZeroPollingResource::make()->definition())
        ->toThrow(InvalidConfigurationException::class, 'DefinitionZeroPollingResource: polling: the interval must be at least 1 second; got 0.');
});

it('sends the definition with the orders index', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $definition = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('resource.definition');

    expect($definition)
        ->toMatchArray(['paginationType' => 'links', 'countStrategy' => 'capped', 'countCap' => 10000, 'bulkInlineMax' => 500, 'pickerResults' => 25, 'idListMax' => 1000, 'clickAction' => 'detail'])
        ->and(array_keys($definition))->toContain('perPage', 'globalSearchResults', 'polling', 'tableStyle', 'defaultGroup');
});
