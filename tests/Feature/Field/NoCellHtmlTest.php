<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('never sends rendered cell HTML in an index response', function (string $resource) {
    $rows = $this->getJson("/lathe/api/resources/{$resource}")->assertOk()->json('table.rows');

    foreach ($rows as $row) {
        foreach ($row['cells'] as $cell) {
            expect($cell['display'])->not->toMatch('/<[a-z][^>]*>/i');
        }
    }
})->with(['orders', 'customers', 'posts', 'products', 'users']);
