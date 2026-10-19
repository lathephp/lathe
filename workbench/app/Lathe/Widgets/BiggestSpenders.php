<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\TableWidget;
use Workbench\App\Models\Customer;

/**
 * The 5 customers with the highest order totals in the range, as a table of their orders and money spent.
 */
class BiggestSpenders extends TableWidget
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
        return [
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
            ['key' => 'spent', 'label' => 'Spent', 'align' => 'right'],
        ];
    }

    public function rows(LatheRequest $request): array
    {
        $since = now()->subDays((int) $request->query('range', '30'));
        $inRange = fn (Builder $query) => $query->where('placed_at', '>=', $since);
        $customers = Customer::withCount(['orders' => $inRange])
            ->withSum(['orders' => $inRange], 'total_cents')
            ->orderByDesc('orders_sum_total_cents')
            ->orderBy('id')
            ->limit(5)
            ->get();
        $rows = [];

        foreach ($customers as $customer) {
            $count = $customer->getAttribute('orders_count');
            $cents = $customer->getAttribute('orders_sum_total_cents');

            $rows[] = [
                'customer' => $customer->name,
                'orders' => is_numeric($count) ? (int) $count : 0,
                'spent' => '$'.number_format(is_numeric($cents) ? $cents / 100 : 0, 2),
            ];
        }

        return $rows;
    }
}
