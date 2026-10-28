<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Preferences\UserPreference;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(30)->run();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('opens the table until the user chooses the Board, then opens the Board', function () {
    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.layout'))->toBe('table');

    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'board'])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.layout'))->toBe('board')
        ->and(UserPreference::where('key', 'layout:orders')->sole()->value)->toBe(['layout' => 'board']);

    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'table'])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.layout'))->toBe('table')
        ->and(UserPreference::where('key', 'layout:orders')->count())->toBe(1);
});

it('lets the URL layout win over the saved one', function () {
    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'board'])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders?layout=table')->assertOk()->json('table.layout'))->toBe('table');

    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'table'])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders?layout=board')->assertOk()->json('table.layout'))->toBe('board');
});

it('keeps each user their own layout', function () {
    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'board'])->assertNoContent();

    $this->actingAs(User::factory()->create());

    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.layout'))->toBe('table');
});

it('opens the table on a lens or a Resource without a board, whatever was chosen', function () {
    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'board'])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders/lenses/pending-orders?layout=board')->assertOk()->json('table.layout'))->toBe('table')
        ->and($this->getJson('/lathe/api/resources/products?layout=board')->assertOk()->json('table.layout'))->toBe('table');
});

it('refuses a layout that is neither table nor board', function () {
    $this->putJson('/lathe/api/resources/orders/layout', ['layout' => 'grid'])->assertUnprocessable()->assertJsonValidationErrors('layout');
});

it('reads the saved columns, preview panel and layout in one query', function () {
    DB::enableQueryLog();
    $this->getJson('/lathe/api/resources/orders')->assertOk();

    expect(collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'lathe_user_preferences')))->toHaveCount(1);
});

it('saves no layout for a Resource without a board', function () {
    $this->putJson('/lathe/api/resources/products/layout', ['layout' => 'board'])->assertNotFound();

    expect(UserPreference::where('key', 'layout:products')->exists())->toBeFalse();
});
