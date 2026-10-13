<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\CurrencyField;
use Lathe\Field\Field;
use Lathe\Field\SelectField;
use Lathe\Groups\Group;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Layout\Section;
use Lathe\Layout\Tab as LayoutTab;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\SearchableRelation;
use Lathe\Summaries\Summary;
use Lathe\Tabs\Tab;
use Workbench\App\Lathe\Filters\OrderCustomer;
use Workbench\App\Lathe\Filters\OrderStatus;
use Workbench\App\Lathe\Lenses\PendingOrders;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;

class Order extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)
            ->title('number')
            ->searchable(['number', new SearchableRelation('customer', 'name')])
            ->globallySearchable()
            ->with(['customer'])
            ->icon('ShoppingCart')
            ->savedTabs()
            ->polling(10)
            ->showPollingToggle();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Section::make('Order', [
                Field::text('Number')->required()->sortable(),
                $this->status(),
                Field::dateTime('Placed', 'placed_at')->sortable()->required(),
                Field::make('Created', 'created_at')->onlyOnDetail()->canSee(fn (LatheRequest $request) => $request->user() instanceof User && $request->user()->email === 'admin@lathephp.com'),
            ]),
            Section::make('Customer', [
                Field::make('Customer', 'customer.name')->exceptOnForms(),
            ]),
            LayoutTab::group(null, [
                LayoutTab::make('Billing', [$this->total()->required()]),
            ]),
        ];
    }

    /**
     * The index keeps its m2 columns in their order; the record pages group them.
     */
    public function fieldsForIndex(LatheRequest $request): array
    {
        return [
            Field::text('Number')->sortable(),
            Field::make('Customer', 'customer.name')->sortable(),
            $this->status(),
            $this->total()->summarize(Summary::sum()->label('Revenue')->format(fn (int|float|string $cents) => number_format((float) $cents / 100, 2, '.', ''))),
            Field::make('Placed', 'placed_at', fn (Model $order) => $order instanceof OrderModel
                ? $order->placed_at->utc()->toIso8601ZuluString()
                : null)->sortable(),
        ];
    }

    public static function replicateExcept(): array
    {
        return ['number'];
    }

    protected function status(): SelectField
    {
        return Field::select('Status')->options(['pending', 'paid', 'shipped', 'cancelled'])->inline();
    }

    protected function total(): CurrencyField
    {
        return Field::currency('Total', 'total_cents')->asMinorUnits()->sortable()->filterable(scale: 100);
    }

    public function filters(LatheRequest $request): array
    {
        return [new OrderStatus, new OrderCustomer];
    }

    public function lenses(LatheRequest $request): array
    {
        return [new PendingOrders];
    }

    public function groups(LatheRequest $request): array
    {
        return [
            Group::make('status')->titlePrefixedWithLabel()->collapsible(),
            Group::make('placed_at', 'placed')->date(),
        ];
    }

    public function tabs(LatheRequest $request): array
    {
        return [
            Tab::make('All'),
            Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where($query->qualifyColumn('status'), 'pending'))
                ->withCount(),
            Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $query) => $query->where($query->qualifyColumn('status'), 'paid')),
        ];
    }
}
