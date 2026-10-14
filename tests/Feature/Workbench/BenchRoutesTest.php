<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Gate;
use Lathe\Notifications\LatheNotification;
use Workbench\App\Http\Middleware\ServerTiming;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('times the orders page in a Server-Timing header', function () {
    $header = $this->get('/lathe/resources/orders')->assertOk()->headers->get('Server-Timing');

    expect($header)->toMatch('/^total;dur=\d+(\.\d+)?$/');
});

it('sends no Server-Timing header from the package itself', function () {
    $kernel = app(Kernel::class);
    $kernel->setGlobalMiddleware(array_values(array_diff($kernel->getGlobalMiddleware(), [ServerTiming::class])));

    expect($this->get('/lathe/resources/orders')->assertOk()->headers->has('Server-Timing'))->toBeFalse()
        ->and((string) shell_exec('grep -rl "Server-Timing" '.escapeshellarg(dirname(__DIR__, 3).'/src')))->toBe('');
});

it('counts the unread notifications on the plain route as the bell does', function () {
    $user = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $user->notify((new LatheNotification)->message('Exported.'));
    $user->notify((new LatheNotification)->message('Imported.'));

    expect($this->getJson('/bench/plain')->assertOk()->json('count'))->toBe(2)
        ->and($this->getJson('/lathe/api/notifications/count')->assertOk()->json('count'))->toBe(2);
});

it('keeps the plain route behind sign in', function () {
    auth()->logout();

    $this->getJson('/bench/plain')->assertUnauthorized();
});

it('stripes the tables for one request that asks for it', function () {
    $striped = fn (array $headers) => $this->get('/lathe/resources/orders', $headers)->viewData('page')['props']['tableStyle']['striped'];

    expect($striped(['X-Bench-Striped' => '1']))->toBeTrue()
        ->and(config('lathe.table.striped'))->toBeFalse();
});
