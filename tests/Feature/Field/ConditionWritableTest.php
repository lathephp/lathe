<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Field\Condition;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class LockedTotals extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll()->trafficCop(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::select('Status')->options(['pending', 'paid', 'shipped', 'cancelled'])->readonly(),
            Field::number('Total', 'total_cents')->readonlyWhen(Condition::field('status')->eq('paid'))->inline(),
            Field::text('Number')->readonly(fn ($record) => $record?->getAttribute('status') === 'paid')->inline(),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(LockedTotals::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('reads a key the user cannot write from the record when it decides a condition', function () {
    $order = Order::findOrFail(12);
    $order->forceFill(['status' => 'paid'])->save();
    $total = $order->total_cents;

    LatheTest::resource('locked-totals')->update(12, ['status' => 'pending', 'total_cents' => 1])->assertOk();

    expect(Order::findOrFail(12)->total_cents)->toBe($total)
        ->and(Order::findOrFail(12)->status)->toBe('paid');
});

it('refuses an inline edit the record locks, with the record in hand', function () {
    Order::findOrFail(12)->forceFill(['status' => 'paid'])->save();

    $this->patchJson('/lathe/api/resources/locked-totals/12/field/total_cents', ['value' => 1, 'status' => 'pending'])->assertForbidden();
    $this->patchJson('/lathe/api/resources/locked-totals/12/field/number', ['value' => 'X'])->assertForbidden();

    expect(Order::findOrFail(12)->number)->not->toBe('X');
});
