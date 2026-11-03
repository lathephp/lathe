<?php

namespace Lathe\Tests\Feature\Workbench;

use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Enums\ActionEventStatus;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * @param  list<int>  $ids
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function orderActionBody(string $action, array $ids, array $fields = []): array
{
    return ['action' => $action, 'selection' => ['mode' => 'ids', 'ids' => $ids], 'fields' => $fields];
}

it('refuses a refund above the order total with a danger result and a failed event', function () {
    $order = Order::findOrFail(1);
    $order->forceFill(['total_cents' => 20953])->save();

    $this->postJson('/lathe/api/resources/orders/action', orderActionBody('refund', [1], ['amount' => 999999]))
        ->assertOk()
        ->assertJsonPath('result.level', 'danger')
        ->assertJsonPath('result.message', 'The refund is more than the order total of $209.53.');

    $row = ActionEvent::sole();

    expect($row->status)->toBe(ActionEventStatus::Failed)
        ->and($row->exception)->toBe('The refund is more than the order total of $209.53.');
});

it('refunds an amount within the order total and changes no data', function () {
    $order = Order::findOrFail(1);
    $order->forceFill(['total_cents' => 20953])->save();

    $this->postJson('/lathe/api/resources/orders/action', orderActionBody('refund', [1], ['amount' => 10]))
        ->assertOk()
        ->assertJsonPath('result.level', 'success')
        ->assertJsonPath('result.message', 'Refunded $10.00.');

    expect($order->fresh()->total_cents)->toBe(20953);
});

it('marks two pending orders as paid', function () {
    Order::whereIn('id', [1, 2])->update(['status' => 'pending']);

    $this->postJson('/lathe/api/resources/orders/action', orderActionBody('mark-paid', [1, 2]))->assertOk();

    expect(Order::whereIn('id', [1, 2])->pluck('status')->unique()->all())->toBe(['paid']);
});

it('gives every Orders action but Export an icon and groups the fulfilment ones', function () {
    $actions = collect($this->getJson('/lathe/api/resources/orders/actions?display=index')->assertOk()->json('actions'));

    expect($actions->whereNull('icon')->pluck('uriKey')->all())->toBe(['export'])
        ->and($actions->pluck('group', 'uriKey')->only(['mark-paid', 'mark-shipped'])->all())->toBe(['mark-paid' => 'Fulfilment', 'mark-shipped' => 'Fulfilment']);
});
