<?php

namespace Lathe\Tests\Feature\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lathe\Events\ResourceUpdated;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use LogicException;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class InlineLog
{
    /** @var list<string> */
    public static array $entries = [];

    public static function add(string $step): void
    {
        self::$entries[] = $step.'@'.DB::transactionLevel();
    }
}

class InlinePolicy
{
    public static bool $inline = true;

    public function viewAny(User $user): bool
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

class InlineOrder extends Resource
{
    public static ?string $policy = InlinePolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Number')->required(),
            Field::select('Status')->options(['pending', 'paid', 'shipped'])->inline()->fillUsing(function (LatheRequest $request, Model $model) {
                InlineLog::add('fill');
                $model->setAttribute('status', $request->input('status'));

                return fn () => InlineLog::add('callback');
            }),
            Field::make('Shout', fn (Order $order) => strtoupper($order->status)),
            Field::text('Customer note', 'number')->readonly()->inline(),
        ];
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('orders.id', '!=', 500);
    }

    public static function beforeUpdate(LatheRequest $request, Model $model): void
    {
        InlineLog::add('beforeUpdate');
    }

    public static function afterUpdate(LatheRequest $request, Model $model): void
    {
        InlineLog::add('afterUpdate');
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(InlineOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    InlineLog::$entries = [];
    InlinePolicy::$inline = true;
    Event::listen(ResourceUpdated::class, fn () => InlineLog::add('ResourceUpdated'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lets only boolean, select, text, number and multi-select fields go inline', function () {
    expect(fn () => Field::textarea('Notes')->inline())->toThrow(LogicException::class, 'Notes')
        ->and(fn () => Field::make('Plain')->inline())->toThrow(LogicException::class, 'Plain')
        ->and(Field::boolean('Active')->inline()->isInlineEditable())->toBeTrue()
        ->and(Field::number('Qty')->inline()->isInlineEditable())->toBeTrue()
        ->and(Field::text('Name')->inline()->isInlineEditable())->toBeTrue()
        ->and(Field::text('Shout', fn () => 'x')->inline()->isInlineEditable())->toBeFalse()
        ->and(Field::text('Locked')->readonly()->inline()->isInlineEditable())->toBeFalse();
});

it('saves one cell and answers with every index cell of the row', function () {
    $response = $this->patchJson('/lathe/api/resources/inline-orders/12/field/status', ['value' => 'shipped'])->assertOk();

    expect($response->json('field'))->toBe('status')
        ->and($response->json('fields.status.value'))->toBe('shipped')
        ->and($response->json('fields.shout.value'))->toBe('SHIPPED')
        ->and(array_keys($response->json('fields')))->toBe(['number', 'status', 'shout'])
        ->and(Order::findOrFail(12)->status)->toBe('shipped')
        ->and($response->json('updatedAt'))->toBeString();
});

it('runs the update flow for one cell with the event inside the transaction', function () {
    $this->patchJson('/lathe/api/resources/inline-orders/12/field/status', ['value' => 'shipped'])->assertOk();

    expect(InlineLog::$entries)->toBe(['beforeUpdate@1', 'fill@1', 'callback@1', 'ResourceUpdated@1', 'afterUpdate@0']);
});

it('refuses a cell that is not inline, a record outside the scope and a failed rule', function () {
    $this->patchJson('/lathe/api/resources/inline-orders/12/field/number', ['value' => 'X'])->assertForbidden();
    $this->patchJson('/lathe/api/resources/inline-orders/12/field/shout', ['value' => 'X'])->assertForbidden();
    $this->patchJson('/lathe/api/resources/inline-orders/500/field/status', ['value' => 'paid'])->assertNotFound();

    $failed = $this->patchJson('/lathe/api/resources/inline-orders/12/field/status', ['value' => 'lost'])->assertUnprocessable();

    expect(array_keys($failed->json('errors')))->toBe(['status']);
});

it('needs the named inlineEdit ability as well as update', function () {
    InlinePolicy::$inline = false;

    $this->patchJson('/lathe/api/resources/inline-orders/12/field/status', ['value' => 'shipped'])->assertForbidden();

    expect(Order::findOrFail(12)->status)->not->toBe('shipped');
});

it('sends the row token, answers a stale one with 409 and the fresh row, then takes the new token', function () {
    $row = $this->getJson('/lathe/api/resources/inline-orders')->assertOk()->json('table.rows.0');
    $id = $row['id'];
    $token = $row['updatedAt'];

    Carbon::setTestNow(now()->addSeconds(2));
    Order::findOrFail($id)->forceFill(['status' => 'paid'])->save();

    $stale = $this->patchJson("/lathe/api/resources/inline-orders/{$id}/field/status", ['value' => 'shipped', '_updated_at' => $token])->assertStatus(409);

    expect($stale->json('fields.status.value'))->toBe('paid')
        ->and($stale->json('updatedAt'))->not->toBe($token);

    $this->patchJson("/lathe/api/resources/inline-orders/{$id}/field/status", ['value' => 'shipped', '_updated_at' => $stale->json('updatedAt')])->assertOk();
    expect(Order::findOrFail($id)->status)->toBe('shipped');
});
