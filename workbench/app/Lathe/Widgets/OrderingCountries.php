<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PieWidget;
use Workbench\App\Models\Customer;

/**
 * How many of the customers who ordered in the range live in each country.
 */
class OrderingCountries extends PieWidget
{
    // Five bars and a row for the rest need no more height than they take.
    protected string $height = 'dynamic';

    protected const COUNTRIES = [
        'AU' => 'Australia', 'BR' => 'Brazil', 'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France',
        'GB' => 'United Kingdom', 'JP' => 'Japan', 'NL' => 'Netherlands', 'SE' => 'Sweden', 'US' => 'United States',
    ];

    public function name(): string
    {
        return 'Customers by country';
    }

    public function calculate(LatheRequest $request): mixed
    {
        $since = now()->subDays((int) $request->query('range', '30'));
        /** @var Builder<Model> $ordered */
        $ordered = Customer::whereHas('orders', fn (Builder $query) => $query->where('placed_at', '>=', $since));
        $this->cacheTags(['orders']);

        return $this->count($request, $ordered, 'country')
            ->label(fn (string $code) => self::COUNTRIES[$code] ?? $code);
    }
}
