<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\AggregateQuery;
use Lathe\Widgets\RadarWidget;
use Workbench\App\Models\Order;

/**
 * The order totals in dollars on each day of the week, Sunday first.
 */
class SalesByWeekday extends RadarWidget
{
    protected const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function name(): string
    {
        return 'Sales by weekday';
    }

    public function calculate(LatheRequest $request): mixed
    {
        // Each driver numbers the weekdays its own way; all of these give 0 for Sunday.
        $weekday = DB::raw(match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'DAYOFWEEK(placed_at) - 1',
            'pgsql' => 'CAST(EXTRACT(DOW FROM placed_at) AS INTEGER)',
            default => "CAST(strftime('%w', placed_at) AS INTEGER)",
        });
        /** @var array<int|string, int|float> $cents the group labels are the weekday numbers */
        $cents = AggregateQuery::for(Order::class)->sum('total_cents')->groupBy($weekday)->partition()->data;

        return $this->result(
            array_map(fn (string $day) => ['key' => strtolower($day), 'label' => $day], self::DAYS),
            [['name' => 'Sales', 'data' => array_map(fn (int $day) => round(($cents[$day] ?? 0) / 100), array_keys(self::DAYS))]],
        );
    }
}
