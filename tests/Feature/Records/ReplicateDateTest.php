<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('prefills a replicated date-time exactly as the edit form does', function () {
    $edit = array_column(LatheTest::resource('orders')->updateFields(9)->assertOk()->json('fields'), null, 'key')['placed_at'];
    $replica = array_column(LatheTest::resource('orders')->replicateFields(9)->assertOk()->json('fields'), null, 'key')['placed_at'];

    expect($replica['value'])->not->toBeNull()->toBe($edit['value'])
        ->and(array_diff_key($replica, ['value' => 1]))->toMatchArray(array_diff_key($edit, ['value' => 1, 'readonly' => 1, 'immutable' => 1]));
});
