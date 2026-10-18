<?php

namespace Lathe\Tests\Feature\Actions;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Bus::fake();
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());
});

it('refuses an action the editor may not run the same way for every order as for three', function () {
    expect(Order::count())->toBe(10000);

    $ids = $this->postJson('/lathe/api/resources/orders/action', [
        'action' => 'mark-shipped',
        'selection' => ['mode' => 'ids', 'ids' => Order::query()->limit(3)->pluck('id')->all()],
        'fields' => [],
    ]);
    $all = $this->postJson('/lathe/api/resources/orders/action', [
        'action' => 'mark-shipped',
        'selection' => ['mode' => 'all'],
        'fields' => [],
    ]);

    $ids->assertForbidden();
    $all->assertForbidden();
    expect($all->json())->toBe($ids->json());
});
