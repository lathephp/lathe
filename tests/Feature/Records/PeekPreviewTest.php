<?php

namespace Lathe\Tests\Feature\Records;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Counts how often a peek or preview resolves its fields. */
class PeekCalls
{
    public static int $resolved = 0;

    public static int $displayed = 0;
}

/** Lets the panel list orders but never view one. */
class PeekDenyingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderModel $order): bool
    {
        return false;
    }
}

class PeekOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number')->showWhenPeeking(),
            Field::make('Status')->showWhenPeeking()->showOnPreview()->resolveUsing(function (mixed $value) {
                PeekCalls::$resolved++;

                return $value;
            })->displayUsing(function (mixed $value) {
                PeekCalls::$displayed++;

                return strtoupper((string) $value);
            }),
            Field::make('Total', 'total_cents'),
        ];
    }
}

class PeekListedOrder extends PeekOrder
{
    public function fieldsForPeeking(LatheRequest $request): array
    {
        return [Field::make('Total', 'total_cents')->showWhenPeeking()];
    }

    public function fieldsForPreview(LatheRequest $request): array
    {
        return [Field::make('Number')->showOnPreview()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(PeekOrder::class, PeekListedOrder::class);
    PeekCalls::$resolved = PeekCalls::$displayed = 0;
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('peeks a record with its title, subtitle, avatar and the fields shown when peeking', function () {
    $json = LatheTest::resource('peek-orders')->peek(12)->assertOk()->json();

    expect(array_keys($json))->toBe(['id', 'title', 'subtitle', 'avatar', 'fields'])
        ->and($json['id'])->toBe(12)
        ->and($json['title'])->toBe('ORD-00099454')
        ->and($json['subtitle'])->toBe('')
        ->and($json['avatar'])->toBeNull()
        ->and(array_column($json['fields'], 'key'))->toBe(['number', 'status'])
        ->and($json['fields'][0]['value'])->toBe('ORD-00099454')
        ->and($json['fields'][0]['display'])->toBe('ORD-00099454')
        ->and($json['fields'][0]['label'])->toBe('Number');
});

it('previews a record with the fields shown in the preview', function () {
    $json = LatheTest::resource('peek-orders')->preview(12)->assertOk()->json();

    expect(array_keys($json))->toBe(['id', 'title', 'subtitle', 'avatar', 'fields'])
        ->and(array_column($json['fields'], 'key'))->toBe(['status']);
});

it('uses fieldsForPeeking and fieldsForPreview when they return fields', function () {
    expect(array_column(LatheTest::resource('peek-listed-orders')->peek(12)->json('fields'), 'key'))->toBe(['total_cents'])
        ->and(array_column(LatheTest::resource('peek-listed-orders')->preview(12)->json('fields'), 'key'))->toBe(['number']);
});

it('resolves each field once and runs no list query', function () {
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    LatheTest::resource('peek-orders')->peek(12)->assertOk();

    expect(PeekCalls::$resolved)->toBe(1)
        ->and(PeekCalls::$displayed)->toBe(1)
        ->and(array_values(array_filter($queries, fn (string $sql) => preg_match('/from ["`]?orders["`]?(\s|$)/i', $sql) === 1)))->toHaveCount(1)
        ->and(implode("\n", $queries))->not->toContain('count(')
        ->and(implode("\n", $queries))->not->toContain('offset');
});

it('answers 404 for a missing record and 403 without the view ability', function () {
    LatheTest::resource('peek-orders')->peek(99999999)->assertNotFound();
    LatheTest::resource('peek-orders')->preview(99999999)->assertNotFound();

    Gate::policy(OrderModel::class, PeekDenyingPolicy::class);
    LatheTest::resource('peek-orders')->peek(12)->assertForbidden();
    LatheTest::resource('peek-orders')->preview(12)->assertForbidden();
});
