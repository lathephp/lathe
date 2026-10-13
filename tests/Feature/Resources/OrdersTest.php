<?php

use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

it('lists orders through the test kit', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    LatheTest::actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    LatheTest::resource('orders')->index()->assertOk();
});
