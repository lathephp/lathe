<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\MetricTableRow;
use Lathe\Widgets\TableWidget;
use Workbench\App\Models\Customer;

/**
 * The 5 customers with the highest order totals.
 */
class TopCustomers extends TableWidget
{
    public function __construct()
    {
        parent::__construct();

        // The rows come from a plain query, so the card names the tables it reads.
        $this->cacheTags(['customers', 'orders']);
    }

    public function name(): string
    {
        return 'Top customers';
    }

    public function columns(): array
    {
        return [];
    }

    public function rows(LatheRequest $request): array
    {
        $customers = Customer::withCount('orders')
            ->withSum('orders', 'total_cents')
            ->orderByDesc('orders_sum_total_cents')
            ->orderBy('id')
            ->limit(5)
            ->get();
        $rows = [];

        foreach ($customers as $customer) {
            $count = $customer->getAttribute('orders_count');
            $cents = $customer->getAttribute('orders_sum_total_cents');

            $rows[] = MetricTableRow::make()
                ->icon(Icon::User)
                ->title($customer->name)
                ->subtitle(sprintf('%d orders, $%s', is_numeric($count) ? $count : 0, number_format(is_numeric($cents) ? $cents / 100 : 0, 2)));
        }

        return $rows;
    }
}
