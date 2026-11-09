<?php

use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    LatheTest::actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

// lathe-demo's Orders list puts "Cancellations" in a third of the row with its own range select,
// which a 390 px probe of a short "Revenue" card never met; the workbench keeps that shape.
it('holds a third-width card above the Orders list with a long title and its own range select', function () {
    $widgets = collect(LatheTest::resource('orders')->widgets()->assertOk()->json('widgets'));
    $long = $widgets->first(fn (array $widget) => mb_strlen($widget['name']) >= mb_strlen('Cancellations') && $widget['ranges'] !== []);

    expect($long)->not->toBeNull()
        ->and($long['width'])->toBe('1/3');
});
