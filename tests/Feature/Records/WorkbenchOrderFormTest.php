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

it('names each field by its label in validation messages', function () {
    $values = array_column(LatheTest::resource('orders')->updateFields(12)->assertOk()->json('fields'), 'value', 'key');
    $items = $values['items'];
    $items[0]['quantity'] = 0;

    $errors = LatheTest::resource('orders')->update(12, [...$values, 'total_cents' => '', 'placed_at' => 'not a date', 'items' => $items])
        ->assertUnprocessable()->json('errors');

    expect($errors['total_cents'][0])->toBe('The Total field is required.')
        ->and($errors['placed_at'][0])->toBe('The Placed field must be a valid date.')
        ->and($errors['items.0.quantity'][0])->toBe('The Quantity field must be at least 1.');
});

it('offers a preview of a workbench order with its status, placed date, number and total', function () {
    $fields = array_column(LatheTest::resource('orders')->preview(12)->assertOk()->json('fields'), 'key');

    expect($fields)->toBe(['status', 'placed_at', 'number', 'total_cents']);
});
