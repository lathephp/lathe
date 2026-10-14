<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * Echoes what the action route received, in place of the real one.
 */
function echoActionRoute(string $uri): void
{
    Route::post($uri, fn (Request $request) => [
        // The test client posts files as form fields, the way a multipart body arrives.
        'multipart' => ! $request->isJson(),
        'selection' => $request->input('selection'),
        'file' => $request->file('fields.manifest')?->getClientOriginalName(),
        'note' => $request->input('fields.note'),
    ]);
}

test('multipart_action_helper_sends_files', function () {
    echoActionRoute('lathe/api/resources/orders/action');

    $response = LatheTest::resource('orders')->runAction('attach-manifest', ['mode' => 'ids', 'ids' => [1]], [
        'manifest' => UploadedFile::fake()->create('manifest.pdf', 10),
        'note' => 'Fragile',
    ]);

    $response->assertOk()->assertJson([
        'multipart' => true,
        'selection' => '{"mode":"ids","ids":[1]}',
        'file' => 'manifest.pdf',
        'note' => 'Fragile',
    ]);
})->group('regression');

it('runs an action as JSON when no field holds a file', function () {
    echoActionRoute('lathe/api/resources/orders/action');

    LatheTest::resource('orders')->runAction('mark-shipped', ['mode' => 'ids', 'ids' => [1, 2]], ['note' => 'Today'])
        ->assertOk()
        ->assertJson(['multipart' => false, 'selection' => ['mode' => 'ids', 'ids' => [1, 2]], 'note' => 'Today']);
});

it('sends the lens twins through the lens routes', function () {
    echoActionRoute('lathe/api/resources/orders/lenses/big-spenders/action');

    LatheTest::resource('orders')->forLens('big-spenders')->postAction('mark-shipped', ['mode' => 'all'])
        ->assertOk()
        ->assertJson(['selection' => ['mode' => 'all']]);
});
