<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Lathe\Lathe;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('counts the same orders in every Sales card at each range', function (int $range, string $zone) {
    Lathe::userTimezone(fn (Request $request) => $zone);
    $this->travelTo(CarbonImmutable::now($zone)->setTime(15, 0));
    $value = fn (string $widget) => $this->getJson("/lathe/api/dashboards/main/widgets/{$widget}?range={$range}")->assertOk()->json('value');
    $orders = $value('orders')['value'];

    expect($orders)->toBeGreaterThan(0)
        ->and(array_sum(array_column($value('status-share')['value'], 'value')))->toBe($orders)
        ->and($value('shipped')['target'])->toBe($orders);
})->with([7, 30])->with(['UTC', 'Asia/Dhaka']);
