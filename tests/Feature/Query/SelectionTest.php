<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Customer;

class FiftyIdsResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->idListMax(50)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

/**
 * A POST to a Resource route carrying the selection field, as an array or as multipart text.
 */
function selectionRequest(mixed $selection, string $resource = 'orders'): LatheRequest
{
    $request = LatheRequest::createFrom(Request::create("/lathe/api/resources/{$resource}/action", 'POST', ['selection' => $selection]));
    $route = new Route('POST', 'lathe/api/resources/{resource}/action', []);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

/**
 * The error the selection field answers with, or null when it parses.
 */
function selectionError(mixed $selection, string $resource = 'orders'): ?string
{
    try {
        selectionRequest($selection, $resource)->selection();
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selection');

        return $e->errors()['selection'][0];
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function selectionBody(): array
{
    return [
        'mode' => 'all',
        'ids' => [],
        'exclusions' => [3, '7'],
        'q' => 'acme',
        'filters' => ['status' => 'paid', 'tag' => ['a', 'b'], 'total' => ['min' => 10]],
        'tab' => 'pending',
        'saved_tab' => '5',
        'trashed' => 'with',
        'lens' => 'pending-orders',
        'via' => 'customers/4/orders',
    ];
}

it('parses the ten keys of a selection body', function () {
    $body = selectionBody();
    $selection = selectionRequest($body)->selection();

    expect($selection)->toBeInstanceOf(Selection::class)
        ->and($selection->isAll())->toBeTrue()
        ->and($selection->mode)->toBe('all')
        ->and($selection->exclusions)->toBe([3, '7'])
        ->and($selection->q)->toBe('acme')
        ->and($selection->filters)->toBe($body['filters'])
        ->and($selection->tab)->toBe('pending')
        ->and($selection->savedTab)->toBe('5')
        ->and($selection->trashed)->toBe('with')
        ->and($selection->lens)->toBe('pending-orders')
        ->and($selection->via)->toBe('customers/4/orders')
        ->and($selection->toArray())->toBe($body);
});

it('parses the JSON string a multipart body carries', function () {
    $selection = selectionRequest(json_encode(selectionBody()))->selection();

    expect($selection->toArray())->toBe(selectionBody());
});

it('round trips through toArray and fromArray', function () {
    expect(Selection::fromArray(selectionBody())->toArray())->toBe(selectionBody());
});

it('parses a mode ids selection with defaults and zero ids', function () {
    $selection = selectionRequest(['mode' => 'ids', 'ids' => []])->selection();

    expect($selection->isAll())->toBeFalse()
        ->and($selection->ids)->toBe([])
        ->and($selection->exclusions)->toBe([])
        ->and($selection->q)->toBeNull()
        ->and($selection->filters)->toBe([])
        ->and($selection->lens)->toBeNull();
});

test('id_list_over_limit_is_422', function () {
    expect(selectionError(['mode' => 'ids', 'ids' => range(1, 1000)]))->toBeNull();

    expect(selectionError(['mode' => 'ids', 'ids' => range(1, 1001)]))->toContain('1,000');
})->group('regression');

it('reads the id list max of the Resource', function () {
    Lathe::registerResources(FiftyIdsResource::class);
    $key = FiftyIdsResource::uriKey();

    expect(selectionError(['mode' => 'ids', 'ids' => range(1, 50)], $key))->toBeNull()
        ->and(selectionError(['mode' => 'ids', 'ids' => range(1, 51)], $key))->toContain('50');
});

it('answers 422 for a selection that is not an object', function (mixed $selection) {
    expect(selectionError($selection))->not->toBeNull();
})->with([
    'text' => ['nope'],
    'broken JSON' => ['{"mode":'],
    'a list' => [[1, 2, 3]],
    'missing' => [null],
]);

it('answers 422 for an unknown mode', function (mixed $mode) {
    expect(selectionError(['mode' => $mode, 'ids' => [1]]))->not->toBeNull();
})->with(['everything', '', null, [['ids']]]);

it('answers 422 for more than 1,000 exclusions', function () {
    expect(selectionError(['mode' => 'all', 'exclusions' => range(1, 1000)]))->toBeNull()
        ->and(selectionError(['mode' => 'all', 'exclusions' => range(1, 1001)]))->toContain('1,000');
});

it('answers 422 for ids that are not scalars', function (mixed $id) {
    expect(selectionError(['mode' => 'ids', 'ids' => [1, $id]]))->not->toBeNull();
})->with([[['a']], [null], [1.5], [true]]);
