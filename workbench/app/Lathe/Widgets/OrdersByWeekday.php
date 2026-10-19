<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\AggregateQuery;
use Lathe\Widgets\BarWidget;
use Workbench\App\Models\Order;

/**
 * The orders placed in the range on each day of the week, Monday first, with the two busiest days in the first chart colour.
 */
class OrdersByWeekday extends BarWidget
{
    protected const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];

    public function name(): string
    {
        return 'Orders by weekday';
    }

    public function calculate(LatheRequest $request): mixed
    {
        // Each driver numbers the weekdays its own way; all of these give 0 for Sunday.
        $weekday = DB::raw(match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'DAYOFWEEK(placed_at) - 1',
            'pgsql' => 'CAST(EXTRACT(DOW FROM placed_at) AS INTEGER)',
            default => "CAST(strftime('%w', placed_at) AS INTEGER)",
        });
        /** @var Builder<Model> $placed */
        $placed = Order::where('placed_at', '>=', now()->subDays((int) $request->query('range', '30')));
        /** @var array<int|string, int|float> $counts the group labels are the weekday numbers */
        $counts = AggregateQuery::for($placed)
            ->count()->groupBy($weekday)->partition()->data;
        $bars = [];

        foreach (self::DAYS as $day => $label) {
            $bars[$label] = $counts[$day] ?? 0;
        }

        $busiest = array_slice(array_keys(array_reverse(collect($bars)->sort()->all(), true)), 0, 2);
        $colors = array_map(fn (string $label) => in_array($label, $busiest, true) ? 'var(--chart-1)' : 'var(--chart-2)', array_combine(self::DAYS, self::DAYS));

        return $this->result($bars)->colors($colors);
    }
}
