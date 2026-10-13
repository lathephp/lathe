<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

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
        ->and(Order::whereRaw("typeof(total_cents) != 'integer'")->count())->toBe(0);
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

    foreach (['orders', 'customers', 'users'] as $table) {
        DB::table($table)->truncate();
    }
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
