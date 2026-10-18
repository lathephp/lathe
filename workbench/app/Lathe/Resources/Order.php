<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Actions\Export\ExportAction;
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
use Workbench\App\Lathe\Actions\CancelOrder;
use Workbench\App\Lathe\Actions\HoldOrder;
use Workbench\App\Lathe\Actions\MarkShipped;
use Workbench\App\Lathe\Filters\OrderCustomer;
use Workbench\App\Lathe\Filters\OrderStatus;
use Workbench\App\Lathe\Lenses\PendingOrders;
use Workbench\App\Lathe\ResourcePlugins\ActivityTimeline;
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
                Field::text('Number')->required()->sortable()->showOnPreview(),
                $this->status()->showOnPreview(),
                Field::dateTime('Placed', 'placed_at')->sortable()->required()->showOnPreview(),
                Field::dateTime('Created', 'created_at')->onlyOnDetail()->canSee(fn (LatheRequest $request) => $request->user() instanceof User && $request->user()->email === 'admin@lathephp.com'),
            ]),
            Section::make('Customer', [
                Field::belongsTo('Customer')->searchable()->creatable()->modalSize('lg'),
            ]),
            LayoutTab::group(null, [
                LayoutTab::make('Billing', [$this->total()->required()->showOnPreview()]),
                // The form edits the items in a repeater; detail lists them in the Items panel.
                LayoutTab::make('Items', [
                    Field::hasMany('Items')->withCount()->collapsible(),
                    Field::repeater('Items')->asHasMany('items')->onlyOnForms()->fields([
                        Field::number('Quantity')->min(1)->required(),
                        Field::currency('Price', 'price_cents')->asMinorUnits()->required(),
                    ])->addable(false)->confirmRemoval()->rowLabel('quantity', 'Item #{{index}}'),
                ]),
            ]),
            ActivityTimeline::make()->heading('Activity timeline'),
        ];
    }

    /**
     * The index keeps its m2 columns in their order; the record pages group them.
     */
    public function fieldsForIndex(LatheRequest $request): array
    {
        return [
            Field::text('Number')->sortable(),
            Field::belongsTo('Customer')->sortable(),
            $this->status(),
            $this->total()->summarize(Summary::sum()->label('Revenue')->format(fn (int|float|string $cents) => (string) $this->total()->formatMoney((float) $cents / 100))),
            Field::dateTime('Placed', 'placed_at')->sortable(),
        ];
    }

    public static function replicateExcept(): array
    {
        return ['number'];
    }

    protected function status(): SelectField
    {
        return Field::select('Status')
            ->options(['pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'])
            ->badges(['pending' => 'warning', 'paid' => 'success', 'shipped' => 'info', 'cancelled' => 'neutral'])
            ->inline();
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

    public function actions(LatheRequest $request): array
    {
        return [ExportAction::make(), new MarkShipped, new CancelOrder, new HoldOrder];
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
