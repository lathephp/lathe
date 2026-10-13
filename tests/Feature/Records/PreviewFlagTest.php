<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class PreviewedOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Number'), Field::make('Status')->showOnPreview()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('offers a row preview only when some field shows in the Preview context', function () {
    Lathe::registerResources(PreviewedOrder::class);

    expect($this->getJson('/lathe/api/resources/previewed-orders')->assertOk()->json('resource.preview'))->toBeTrue()
        ->and($this->getJson('/lathe/api/resources/orders')->assertOk()->json('resource.preview'))->toBeFalse();
});
