<?php

namespace Lathe\Tests\Feature\Actions\Run;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class ChangesOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Status')];
    }

    public function actions(LatheRequest $request): array
    {
        return [
            Action::make()->withName('Cancel')->withUriKey('cancel')->handleUsing(function (ActionFields $fields, Collection $models) {
                foreach ($models as $order) {
                    $order->forceFill(['status' => 'cancelled'])->save();
                }
            }),
            Action::make()->withName('Look')->withUriKey('look')->handleUsing(fn () => null),
            Action::make()->withName('Rename Customer')->withUriKey('rename-customer')->handleUsing(function (ActionFields $fields, Collection $models) {
                foreach ($models as $order) {
                    $order->customer->forceFill(['name' => 'Renamed'])->save();
                }
            }),
        ];
    }
}

class ChangesUsers extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(User::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name'), Field::password('Pin', 'pin_code')];
    }

    public function actions(LatheRequest $request): array
    {
        return [
            Action::make()->withName('Reset')->withUriKey('reset')->handleUsing(function (ActionFields $fields, Collection $models) {
                foreach ($models as $user) {
                    $user->forceFill(['name' => 'Reset User', 'password' => 'new-secret', 'remember_token' => 'fresh-token'])->save();
                }
            }),
        ];
    }
}

/**
 * @param  list<int>  $ids
 */
function runChanges(string $resource, string $action, array $ids): void
{
    test()->postJson("/lathe/api/resources/{$resource}/action", ['action' => $action, 'selection' => ['mode' => 'ids', 'ids' => $ids], 'fields' => []])->assertOk();
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(ChangesOrders::class, ChangesUsers::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('records the values an action changed as original and changes', function () {
    Order::whereKey(12)->update(['status' => 'pending']);

    runChanges('changes-orders', 'cancel', [12]);

    $event = ActionEvent::sole();

    expect($event->original)->toBe(['status' => 'pending'])
        ->and($event->changes)->toBe(['status' => 'cancelled']);
});

it('records no original and no changes for an action that changed nothing', function () {
    runChanges('changes-orders', 'look', [12, 13]);

    expect(ActionEvent::pluck('original')->all())->toBe([null, null])
        ->and(ActionEvent::pluck('changes')->all())->toBe([null, null]);
});

it('records nothing on the record for an action that saved a related record', function () {
    runChanges('changes-orders', 'rename-customer', [12]);

    $event = ActionEvent::sole();

    expect(Order::findOrFail(12)->customer->name)->toBe('Renamed')
        ->and($event->original)->toBeNull()
        ->and($event->changes)->toBeNull();
});

it('keeps hidden and secret attributes out of original and changes', function () {
    DB::table('users')->where('email', 'admin@lathephp.com')->update(['name' => 'Admin']);

    runChanges('changes-users', 'reset', [User::where('email', 'admin@lathephp.com')->value('id')]);

    $event = ActionEvent::sole();

    expect($event->original)->toBe(['name' => 'Admin'])
        ->and($event->changes)->toBe(['name' => 'Reset User']);
});

it('reads the records back once per chunk, not once per record', function () {
    $reads = function (array $ids): int {
        ActionEvent::query()->delete();
        $count = 0;
        DB::listen(function ($query) use (&$count) {
            if (preg_match('/^select .* from .?orders.? where/i', $query->sql) === 1) {
                $count++;
            }
        });

        Model::preventLazyLoading();
        runChanges('changes-orders', 'cancel', $ids);
        Model::preventLazyLoading(false);

        return $count;
    };

    Order::query()->update(['status' => 'pending']);
    $small = $reads(range(1, 10));
    DB::flushQueryLog();
    $large = $reads(range(11, 210));

    expect($large - $small)->toBeLessThanOrEqual(2)
        ->and(ActionEvent::whereNotNull('changes')->count())->toBe(200);
});
