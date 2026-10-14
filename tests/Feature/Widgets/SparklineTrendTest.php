<?php

use Illuminate\Support\Facades\DB;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Widgets\Results\TrendResult;
use Lathe\Widgets\TrendWidget;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SparkTrend extends TrendWidget
{
    public static int $calls = 0;

    public function calculate(LatheRequest $request): mixed
    {
        static::$calls++;

        // One query, as a real trend runs.
        Order::count();

        return new TrendResult(['2026-10-12' => 2, '2026-10-13' => 5, '2026-10-14' => 3]);
    }
}

class SparkTrendOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Number'), Field::sparkline('Trend')->data(new SparkTrend)];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Lathe::registerResources(SparkTrendOrders::class);
    SparkTrend::$calls = 0;
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('draws one trend series on every row of a 100-row page with no query per row', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $rows = $this->getJson('/lathe/api/resources/spark-trend-orders?per_page=100')->assertOk()->json('table.rows');
    $hundred = $queries;
    $queries = 0;
    $this->getJson('/lathe/api/resources/spark-trend-orders?per_page=25')->assertOk();

    expect($rows)->toHaveCount(100)
        ->and(array_unique(array_map(fn (array $row) => json_encode($row['cells']['trend']['value']), $rows)))->toBe(['[2,5,3]'])
        ->and(SparkTrend::$calls)->toBe(2)
        ->and($hundred)->toBe($queries);
});
