<?php

namespace Lathe\Tests\Feature\Resources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class UndoOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll()->trafficCop();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number')->required(), Field::select('Status')->options(['pending', 'paid', 'shipped', 'cancelled'])->inline()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(UndoOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Order::findOrFail(12)->forceFill(['status' => 'paid'])->save();
    ActionEvent::query()->delete();
    Carbon::setTestNow(now()->addSeconds(2));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('saves the previous value back as a second change that the history records', function () {
    $url = '/lathe/api/resources/undo-orders/12/field/status';
    $first = $this->patchJson($url, ['value' => 'shipped'])->assertOk();

    Carbon::setTestNow(now()->addSeconds(2));
    $undo = $this->patchJson($url, ['value' => 'paid', '_updated_at' => $first->json('updatedAt')])->assertOk();

    expect($undo->json('fields.status.value'))->toBe('paid')
        ->and(Order::findOrFail(12)->status)->toBe('paid')
        ->and(ActionEvent::query()->where('type', 'update')->where('actionable_id', '12')->count())->toBe(2);
});

it('answers an undo after someone else changed the record with 409 and keeps their value', function () {
    $url = '/lathe/api/resources/undo-orders/12/field/status';
    $first = $this->patchJson($url, ['value' => 'shipped'])->assertOk();

    Carbon::setTestNow(now()->addSeconds(2));
    Order::findOrFail(12)->forceFill(['status' => 'cancelled'])->save();

    Carbon::setTestNow(now()->addSeconds(2));
    $stale = $this->patchJson($url, ['value' => 'paid', '_updated_at' => $first->json('updatedAt')])->assertStatus(409);

    expect($stale->json('fields.status.value'))->toBe('cancelled')
        ->and(Order::findOrFail(12)->status)->toBe('cancelled');
});
