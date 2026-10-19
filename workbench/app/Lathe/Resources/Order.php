<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Actions\Export\ExportAction;
use Lathe\Field\CurrencyField;
use Lathe\Field\Field;
use Lathe\Field\SelectField;
use Lathe\Groups\Group;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Enums\Icon;
use Lathe\Layout\Section;
use Lathe\Menu\Badge;
use Lathe\Menu\MenuItem;
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
            ->group('Store')
            ->title('number')
            ->globalSearchSubtitle(function (Model $order) {
                assert($order instanceof OrderModel);

                return implode(' · ', [$order->customer->name, $order->placed_at->format('M j, Y'), $this->total()->formatMoney($order->total_cents / 100)]);
            })
            // Contains, so "warren" finds the orders of "Prof. Warren Shields II".
            ->searchable(['number', new SearchableRelation('customer', 'name', 'contains')])
            ->globallySearchable()
            ->globalSearchResults(3)
            ->with(['customer'])
            ->icon('ShoppingCart')
            ->recordHeader(meta: 'placed_at')
            ->savedTabs()
            ->polling(10)
            ->showPollingToggle();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            // The form's two cards: the order, with its customer first and full width, then its items.
            Section::make('Order', [
                Field::belongsTo('Customer')->searchable()->creatable(),
                $this->status()->required()->default('pending')->showOnPreview(),
                Field::dateTime('Placed', 'placed_at')->sortable()->required()->showOnPreview(),
                Field::text('Number')->required()->sortable()->showOnPreview(),
                Field::dateTime('Created', 'created_at')->onlyOnDetail()->canSee(fn (LatheRequest $request) => $request->user() instanceof User && $request->user()->email === 'admin@lathephp.com'),
            ]),
            // Detail lists the items in the Items tab; the form edits them in the repeater below.
            Field::hasMany('Items')->withCount()->collapsible(),
            Section::make('Items', [
                Field::repeater('Items')->asHasMany('items')->onlyOnForms()->fields([
                    Field::number('Quantity')->min(1)->required(),
                    Field::currency('Price', 'price_cents')->asMinorUnits()->required(),
                ])->addable(false)->confirmRemoval()->grid(2)->rowLabel('quantity', 'Item #{{index}}'),
                $this->total()->required()->showOnPreview(),
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

    /**
     * The sidebar entry counts the orders that wait, cached per user for a minute.
     */
    public static function menu(LatheRequest $request): ?MenuItem
    {
        return MenuItem::resource('orders')->icon(Icon::ShoppingCart)
            ->withBadge(fn () => number_format(OrderModel::query()->where('status', 'pending')->count()), 60, Badge::WARNING);
    }

    public static function replicateExcept(): array
    {
        return ['number'];
    }

    protected function status(): SelectField
    {
        return Field::select('Status')
            ->options(['pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'])
            ->badges(['pending' => 'warning', 'paid' => 'info', 'shipped' => 'success', 'cancelled' => 'neutral'])
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
            Tab::make('All')->withCount(),
            Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where($query->qualifyColumn('status'), 'pending'))
                ->withCount()
                ->badgeType('warning'),
            Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $query) => $query->where($query->qualifyColumn('status'), 'paid'))
                ->withCount(),
        ];
    }
}
