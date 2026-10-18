<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Lathe\Actions\Import\FailedImportRow;
use Lathe\Actions\Import\Import;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Storage::fake('local');
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

/**
 * An import of the admin's in the given state, with 25 failed rows and an error file when asked.
 *
 * @param  array<string, mixed>  $attributes
 */
function adminImport(array $attributes = [], bool $withErrors = false): Import
{
    Storage::disk('local')->put('lathe-imports/products.csv', "Name,SKU,Price\nTrail Mug,SKU-90001,12.50\n");
    $import = Import::create([
        'user_id' => (string) test()->admin->id,
        'file_disk' => 'local',
        'file_name' => 'lathe-imports/products.csv',
        'original_name' => 'products.csv',
        'model_class' => Product::class,
        'column_map' => [0 => 'name', 1 => 'sku', 2 => 'price'],
        ...$attributes,
    ]);

    if ($withErrors) {
        for ($row = 2; $row <= 26; $row++) {
            FailedImportRow::create(['import_id' => $import->id, 'row_number' => $row, 'data' => ['price' => 'abc'], 'errors' => ['price' => ['The price field must be a number.']]]);
        }

        Storage::disk('local')->put("lathe-imports/errors-{$import->id}.csv", "_row_number,price,_errors\n");
        $import->update(['error_file' => "lathe-imports/errors-{$import->id}.csv"]);
    }

    return $import;
}

function errorsUrl(Import $import, int $hours = 24): string
{
    return URL::temporarySignedRoute('lathe.api.imports.downloadErrors', now()->addHours($hours), ['import' => $import->id]);
}

it('shows the owner the status of an import', function () {
    $import = adminImport([
        'status' => 'completed', 'mode' => 'dry_run', 'total_rows' => 30, 'processed_rows' => 30,
        'created_rows' => 3, 'updated_rows' => 1, 'skipped_rows' => 1, 'failed_rows' => 25,
    ], withErrors: true);

    $json = $this->actingAs($this->admin)->getJson("/lathe/api/imports/{$import->id}")->assertOk()->json();

    expect(array_keys($json))->toBe([
        'id', 'status', 'mode', 'total_rows', 'processed_rows', 'created_rows', 'updated_rows', 'skipped_rows',
        'failed_rows', 'errors_url', 'exception', 'first_errors', 'resource_label',
    ])
        ->and(array_slice($json, 0, 9))->toBe([
            'id' => $import->id, 'status' => 'completed', 'mode' => 'dry_run', 'total_rows' => 30, 'processed_rows' => 30,
            'created_rows' => 3, 'updated_rows' => 1, 'skipped_rows' => 1, 'failed_rows' => 25,
        ])
        ->and($json['errors_url'])->toContain("/lathe/api/imports/{$import->id}/download-errors?expires=")
        ->and($json['exception'])->toBeNull()
        ->and($json['first_errors'])->toHaveCount(20)
        ->and($json['first_errors'][0])->toBe(['row_number' => 2, 'errors' => ['price' => ['The price field must be a number.']]])
        ->and($json['resource_label'])->toBe('Products');
});

it('gives no errors url without an error file and 404 to another user', function () {
    $import = adminImport(['status' => 'processing']);

    $this->actingAs($this->admin)->getJson("/lathe/api/imports/{$import->id}")->assertOk()
        ->assertJsonPath('errors_url', null)->assertJsonPath('first_errors', []);
    $this->actingAs($this->editor)->getJson("/lathe/api/imports/{$import->id}")->assertNotFound();
    $this->actingAs($this->admin)->getJson('/lathe/api/imports/999')->assertNotFound();
});

it('resumes a failed import for its owner only', function () {
    $failed = adminImport(['status' => 'failed', 'exception' => 'Worker stopped.']);
    $completed = adminImport(['status' => 'completed']);

    $this->actingAs($this->editor)->postJson("/lathe/api/imports/{$failed->id}/resume")->assertNotFound();
    $this->actingAs($this->admin)->postJson("/lathe/api/imports/{$completed->id}/resume")->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/lathe/api/imports/{$failed->id}/resume")->assertStatus(202);

    $failed->refresh();
    expect([$failed->status, $failed->created_rows, $failed->exception])->toBe(['completed', 1, null])
        ->and(Product::where('sku', 'SKU-90001')->exists())->toBeTrue();
});

it('downloads the error CSV from a signed URL for the owner only', function () {
    $import = adminImport(['status' => 'completed', 'failed_rows' => 25], withErrors: true);

    $this->actingAs($this->admin)->get(errorsUrl($import))->assertOk()
        ->assertDownload("errors-{$import->id}.csv");
    $this->actingAs($this->admin)->getJson("/lathe/api/imports/{$import->id}/download-errors")->assertForbidden();
    $this->actingAs($this->editor)->get(errorsUrl($import))->assertForbidden();

    $url = errorsUrl($import, 1);
    $this->travel(2)->hours();
    $this->actingAs($this->admin)->get($url)->assertForbidden();
});

it('answers 404 to a download with no error file', function () {
    $import = adminImport(['status' => 'completed']);

    $this->actingAs($this->admin)->get(errorsUrl($import))->assertNotFound();
});
