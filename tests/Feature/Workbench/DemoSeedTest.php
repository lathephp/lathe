<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Console\Commands\DemoSeedCommand;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertExitCode(0);
});

function seedDemo(): void
{
    test()->seed(DatabaseSeeder::class);
}

it('seeds 10 users, 1,000 customers and 10,000 orders', function () {
    seedDemo();

    expect(User::count())->toBe(10)
        ->and(Customer::count())->toBe(1000)
        ->and(Order::count())->toBe(10000);
});

it('creates the named users with password and a verified email', function () {
    seedDemo();

    expect(User::where('email', 'admin@lathephp.com')->value('name'))->toBe('Admin User')
        ->and(User::where('email', 'editor@lathephp.com')->value('name'))->toBe('Editor User')
        ->and(User::where('email', 'guest@lathephp.com')->exists())->toBeTrue()
        ->and(User::where('name', 'like', 'Ada%')->count())->toBe(1)
        ->and(User::whereNull('email_verified_at')->count())->toBe(0);

    foreach (User::all() as $user) {
        expect(Hash::check('password', $user->password))->toBeTrue();
    }
});

it('creates customers with the names and emails the demos search for', function () {
    seedDemo();

    expect(Customer::where('name', 'like', 'Ann%')->count())->toBeGreaterThanOrEqual(3)
        ->and(Customer::where('name', 'like', 'Ada%')->count())->toBeGreaterThanOrEqual(1)
        ->and(Customer::where('email', 'like', '%@gmail.com')->count())->toBeGreaterThanOrEqual(3)
        ->and(Customer::whereKey(12)->exists())->toBeTrue()
        ->and(Order::whereKey(12)->exists())->toBeTrue();
});

it('creates orders with unique 12 character numbers, all four statuses and integer totals', function () {
    seedDemo();

    expect(Order::distinct()->count('number'))->toBe(10000)
        ->and(Order::whereRaw('length(number) != 12')->count())->toBe(0)
        ->and(Order::distinct()->orderBy('status')->pluck('status')->all())
        ->toBe(['cancelled', 'paid', 'pending', 'shipped'])
        ->and(DB::table('orders')->pluck('total_cents')->reject(fn ($cents) => is_int($cents))->count())->toBe(0);
});

it('spreads placed_at over the last 365 days and rises toward today', function () {
    seedDemo();

    $orders = Order::orderBy('id')->pluck('placed_at');
    $mean = fn ($rows) => $rows->map(fn ($at) => Carbon::parse($at)->getTimestamp())->avg();

    expect(Carbon::parse($orders->min())->gte(now()->subDays(365)))->toBeTrue()
        ->and(Carbon::parse($orders->max())->lte(now()))->toBeTrue()
        ->and($mean($orders->slice(-1000)))->toBeGreaterThan($mean($orders->take(1000)));
});

it('seeds the same orders on every fresh database', function () {
    seedDemo();
    $first = [Order::orderBy('id')->value('number'), Order::orderByDesc('id')->value('number')];

    // MySQL and MariaDB refuse to truncate customers while orders points at it.
    Schema::withoutForeignKeyConstraints(function () {
        foreach (DemoSeedCommand::TABLES as $table) {
            DB::table($table)->truncate();
        }
    });
    seedDemo();

    expect([Order::orderBy('id')->value('number'), Order::orderByDesc('id')->value('number')])->toBe($first);
});

it('seeds only when no user exists', function () {
    seedDemo();
    seedDemo();

    expect(User::count())->toBe(10)
        ->and(Customer::count())->toBe(1000)
        ->and(Order::count())->toBe(10000);
});

it('reseeds the default store with lathe:demo-seed', function () {
    seedDemo();
    User::where('email', 'guest@lathephp.com')->delete();
    Order::where('id', '<', 6)->delete();

    $this->artisan('lathe:demo-seed')->assertExitCode(0);

    expect(User::count())->toBe(10)
        ->and(Customer::count())->toBe(1000)
        ->and(Order::count())->toBe(10000);
});

it('seeds the catalog, the posts and items for the first 10,000 orders', function () {
    seedDemo();

    $perOrder = DB::table('order_items')->selectRaw('order_id, count(*) as item_count')->groupBy('order_id')->pluck('item_count');

    expect(DB::table('products')->count())->toBe(500)
        ->and(DB::table('categories')->count())->toBe(20)
        ->and(DB::table('tags')->count())->toBe(50)
        ->and(DB::table('posts')->count())->toBe(200)
        ->and(DB::table('posts')->whereNotNull('deleted_at')->count())->toBe(10)
        ->and(DB::table('comments')->count())->toBe(1000)
        ->and($perOrder->count())->toBe(10000)
        ->and((int) DB::table('order_items')->max('order_id'))->toBe(10000)
        ->and((int) $perOrder->min())->toBeGreaterThanOrEqual(1)
        ->and((int) $perOrder->max())->toBeLessThanOrEqual(5);
});

it('seeds the orders it is asked for, with one customer per 100 orders and at least 1,000', function () {
    $this->artisan('lathe:demo-seed', ['--orders' => 1500])->expectsOutputToContain('Seeded 1,500 orders in')->assertExitCode(0);

    expect(Order::count())->toBe(1500)
        ->and(Customer::count())->toBe(1000)
        ->and((new DemoSeeder)->forOrders(250000)->customerCount())->toBe(2500);
});

it('refuses an order count that is not a whole number', function (string $orders) {
    $this->artisan('lathe:demo-seed', ['--orders' => $orders])->assertExitCode(1);
})->with(['0', 'abc', '-5']);

it('gives two seeds of 1,000 orders equal checksums', function () {
    $checksum = fn () => md5(DB::table('orders')->orderBy('id')->get()->toJson());

    $this->artisan('lathe:demo-seed', ['--orders' => 1000])->assertExitCode(0);
    $first = $checksum();
    $this->artisan('lathe:demo-seed', ['--orders' => 1000])->assertExitCode(0);

    expect($checksum())->toBe($first);
});

it('indexes the orders columns the list filters, sorts and searches on', function () {
    $indexes = collect(Schema::getIndexes('orders'))->map(fn (array $index) => [implode(',', $index['columns']), $index['unique']]);

    expect($indexes)->toContain(['number', true], ['customer_id', false], ['status', false], ['total_cents', false], ['status,total_cents', false], ['placed_at', false]);
});
