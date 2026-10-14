<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Import\Import;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

it('shows an export run to the user who started it', function () {
    $export = Export::create(['user_id' => $this->admin->id, 'file_disk' => 'local']);

    $this->actingAs($this->admin)->get("/lathe/runs/export/{$export->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Runs/Show', shouldExist: false)->where('type', 'export')->where('id', $export->id));
});

it('shows an import run with notifications off', function () {
    config(['lathe.notifications.enabled' => false]);
    $import = Import::create(['user_id' => $this->admin->id, 'file_name' => 'a.csv', 'model_class' => User::class]);

    $this->actingAs($this->admin)->get("/lathe/runs/import/{$import->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Runs/Show', shouldExist: false)->where('type', 'import'));
});

it('answers 404 to another user and for an unknown run', function () {
    $export = Export::create(['user_id' => $this->admin->id, 'file_disk' => 'local']);

    $this->actingAs($this->editor)->get("/lathe/runs/export/{$export->id}")->assertNotFound();
    $this->actingAs($this->admin)->get('/lathe/runs/import/999')->assertNotFound();
    $this->actingAs($this->admin)->get("/lathe/runs/bulk/{$export->id}")->assertNotFound();
});
