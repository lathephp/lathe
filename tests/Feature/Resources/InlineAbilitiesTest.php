<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Resources\TrafficCop;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class InlineAbilitiesPolicy
{
    public static bool $inline = true;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return true;
    }

    public function inlineEdit(User $user, Order $order): bool
    {
        return static::$inline;
    }
}

class InlineAbilitiesOrder extends Resource
{
    public static ?string $policy = InlineAbilitiesPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Number'),
            Field::select('Status')->options(['pending', 'paid', 'shipped'])->inline(),
        ];
    }
}

class PlainAbilitiesOrder extends Resource
{
    public static ?string $policy = InlineAbilitiesPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number')];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(InlineAbilitiesOrder::class, PlainAbilitiesOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    InlineAbilitiesPolicy::$inline = true;
});

it('tells each index row whether its inline cells may be edited', function () {
    $rows = $this->getJson('/lathe/api/resources/inline-abilities-orders')->assertOk()->json('table.rows');

    expect($rows)->not->toBe([])
        ->and(array_unique(array_column(array_column($rows, 'abilities'), 'inlineEdit')))->toBe([true]);

    InlineAbilitiesPolicy::$inline = false;

    $rows = $this->getJson('/lathe/api/resources/inline-abilities-orders')->assertOk()->json('table.rows');

    expect(array_unique(array_column(array_column($rows, 'abilities'), 'inlineEdit')))->toBe([false])
        ->and(array_unique(array_column(array_column($rows, 'abilities'), 'update')))->toBe([true]);
});

it('sends no inlineEdit ability for an index without an inline column', function () {
    $rows = $this->getJson('/lathe/api/resources/plain-abilities-orders')->assertOk()->json('table.rows');

    expect($rows)->not->toBe([])
        ->and($rows[0]['abilities'])->not->toHaveKey('inlineEdit')
        ->and($rows[0]['abilities'])->toHaveKey('update');
});

it('sends the row token and the inlineEdit ability on a relation panel row', function () {
    $customer = Order::findOrFail(12)->customer_id;

    $rows = $this->getJson("/lathe/api/resources/customers/{$customer}/orders?per_page=100")->assertOk()->json('rows');
    $row = collect($rows)->firstWhere('related_id', 12);

    expect($row['updatedAt'])->toBeString()->toBe(TrafficCop::token(Order::findOrFail(12)))
        ->and($row['abilities']['inlineEdit'])->toBeTrue();
});
