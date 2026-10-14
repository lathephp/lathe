<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Tests\Benchmark\ScaleFixture;
use Workbench\App\Models\User;

it('registers 500 scale Resources, each with an action, and builds only the one a request uses', function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $root = ScaleFixture::install();
    $this->artisan('lathe:cache')->assertSuccessful();
    Lathe::resources()->flush();

    $scale = array_filter(Lathe::resources()->all(), fn (string $class) => str_contains($class, '\\Resources\\ScaleThing'));

    $this->actingAs(User::forceCreate(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => bcrypt('secret')]))
        ->getJson('/lathe/api/resources/scale-thing7s')->assertOk();
    $this->getJson('/lathe/api/resources/scale-thing7s/lenses/scale-lens')->assertOk();
    DB::table('scale_items')->insert(['name' => 'One', 'status' => 'open']);
    $this->get('/lathe/resources/scale-thing7s/'.DB::table('scale_items')->value('id'))->assertOk();

    expect($scale)->toHaveCount(500)
        ->and(Lathe::resources()->definitionsBuilt())->toBe(1);

    Lathe::resources()->clearCache();
    File::deleteDirectory($root);
});
