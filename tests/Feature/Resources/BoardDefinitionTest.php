<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class BoardDefinitionOrder extends Resource
{
    /** @var Closure(): list<mixed> */
    public static Closure $fields;

    public static string $board = 'status';

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->board(static::$board);
    }

    public function fields(LatheRequest $request): array
    {
        return (static::$fields)();
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(30)->run();
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(BoardDefinitionOrder::class);
    BoardDefinitionOrder::$board = 'status';
    BoardDefinitionOrder::$fields = fn () => [Field::text('Number'), Field::select('Status')->options(['pending' => 'Pending', 'paid' => 'Paid'])->inline()];
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('sends the Orders board: the status field with its options in order and its meter, and no No value column', function () {
    $board = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.board');

    expect($board['field']['key'])->toBe('status')
        ->and(array_column($board['field']['options'], 'label'))->toBe(['Pending', 'Paid', 'Shipped', 'Cancelled'])
        ->and($board['field']['meter'])->toBe(['pending', 'paid', 'shipped'])
        ->and($board['noValue'])->toBeFalse();
});

it('adds a No value column when the board field is nullable', function () {
    BoardDefinitionOrder::$fields = fn () => [Field::select('Status')->options(['pending', 'paid'])->nullable()->inline()];

    expect($this->getJson('/lathe/api/resources/board-definition-orders')->assertOk()->json('table.board.noValue'))->toBeTrue();
});

it('sends no board for a Resource without one, or on a lens', function () {
    expect($this->getJson('/lathe/api/resources/products')->assertOk()->json('table'))->toHaveKey('board', null)
        ->and($this->getJson('/lathe/api/resources/orders/lenses/pending-orders')->assertOk()->json('table'))->toHaveKey('board', null);
});

it('stops boot naming the field when the board field cannot drive a board', function (string $board, Closure $fields, string $message) {
    BoardDefinitionOrder::$board = $board;
    BoardDefinitionOrder::$fields = $fields;

    expect(fn () => Lathe::resources()->definition(BoardDefinitionOrder::class))->toThrow(LogicException::class, $message);
})->with([
    'thirteen options' => ['status', fn () => [Field::select('Status')->options(range(1, 13))->inline()], 'The board field [Status] has 13 options; a board takes at most 12.'],
    'not inline' => ['status', fn () => [Field::select('Status')->options(['pending', 'paid'])], 'The board field [Status] must be inline: call ->inline() on it.'],
    'not a select' => ['number', fn () => [Field::text('Number')->inline()], 'The board field [Number] must be a select field.'],
    'remote options' => ['customer_id', fn () => [Field::select('Customer', 'customer_id')->remoteOptions(fn () => User::query(), 'name')->inline()], 'The board field [Customer] needs inline options, not remote ones.'],
    'missing' => ['stage', fn () => [Field::text('Number')], 'The board field [stage] is not an index field of '],
]);
