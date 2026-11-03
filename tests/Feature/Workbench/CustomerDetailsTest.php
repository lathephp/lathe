<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Customer;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('creates a customer with its optional Details left as the create form loads them', function () {
    $fields = collect($this->getJson('/lathe/api/resources/customers/creation-fields')->assertOk()->json('fields'));
    $details = $fields->firstWhere('attribute', 'details');

    expect($details['component'])->toBe('key-value-field')
        ->and($details['required'])->toBeFalse();

    // The create page and the Create side sheet both post the form's values to this endpoint.
    $id = $this->postJson('/lathe/api/resources/customers', [
        'name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'country' => 'BD', 'type' => 'person', 'details' => $details['value'],
    ])->assertCreated()->json('id');

    expect(Customer::findOrFail($id)->details)->toBe([]);
});
