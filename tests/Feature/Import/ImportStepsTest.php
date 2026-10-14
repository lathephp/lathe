<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Lathe\Actions\Import\Import;
use Lathe\Testing\LatheTest;
use Lathe\Uploads\PendingUpload;
use Symfony\Component\HttpFoundation\Response;
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
    LatheTest::actingAs($this->admin);
});

/**
 * @param  array<string, mixed>  $body
 * @return TestResponse<Response>
 */
function startImportUpload(array $body): TestResponse
{
    return LatheTest::resource('products')->startActionUpload('import', 'file', $body);
}

/**
 * @return TestResponse<Response>
 */
function putImportChunk(string $id, string $bytes): TestResponse
{
    return test()->call('PUT', "/lathe/api/uploads/{$id}/chunks/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes);
}

/**
 * Send a file through the import's upload route in one chunk, as the modal does, and answer its id.
 */
function sendImportFile(string $name, string $bytes): string
{
    $id = (string) startImportUpload(['name' => $name, 'size' => strlen($bytes), 'mime' => 'text/csv'])->assertCreated()->json('upload_id');
    putImportChunk($id, $bytes)->assertNoContent();
    test()->postJson("/lathe/api/uploads/{$id}/complete")->assertOk();

    return $id;
}

/**
 * @param  array<string, mixed>  $fields
 * @return TestResponse<Response>
 */
function importStep(array $fields): TestResponse
{
    return LatheTest::resource('products')->postAction('import', ['mode' => 'ids', 'ids' => []], $fields);
}

function importFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 3)."/workbench/fixtures/{$name}");
}

it('refuses the import and its upload without the import ability', function () {
    LatheTest::actingAs($this->editor);

    startImportUpload(['name' => 'products.csv', 'size' => 100, 'mime' => 'text/csv'])->assertForbidden();
    importStep(['import_step' => 'upload', 'file' => '01UPLOAD'])->assertForbidden();
    importStep(['import_step' => 'dry_run', 'import_id' => 1, 'column_map' => ['name']])->assertForbidden();
});

it('opens an upload for the import file', function () {
    $response = startImportUpload(['name' => 'products.csv', 'size' => 100, 'mime' => 'text/csv'])->assertCreated();

    expect($response->json('upload_id'))->toBeString()->toHaveLength(26)
        ->and($response->json('chunk_size'))->toBe(5242880);
});

it('caps the import file at imports.max_file_size_mb on start and on complete', function () {
    config(['lathe.imports.max_file_size_mb' => 1]);

    startImportUpload(['name' => 'products.csv', 'size' => 2 * 1024 * 1024, 'mime' => 'text/csv'])
        ->assertUnprocessable()->assertJsonValidationErrors('size');

    // The start declared exactly the cap; the chunk sent makes the file grow past it.
    $id = (string) startImportUpload(['name' => 'products.csv', 'size' => 1024 * 1024, 'mime' => 'text/csv'])->assertCreated()->json('upload_id');
    putImportChunk($id, "Name,SKU,Price\n".str_repeat("Mug,SKU-1,1\n", 180000))->assertNoContent();

    $this->postJson("/lathe/api/uploads/{$id}/complete")->assertUnprocessable();
});

it('takes only csv and xlsx files', function () {
    startImportUpload(['name' => 'products.txt', 'size' => 100, 'mime' => 'text/plain'])
        ->assertUnprocessable()->assertJsonValidationErrors('mime');
    startImportUpload(['name' => 'products.xlsx', 'size' => 100, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
        ->assertCreated();
});

it('stores the uploaded file and answers its headers, preview and mapping', function () {
    $id = sendImportFile('products.csv', importFixture('products.csv'));

    $response = importStep(['import_step' => 'upload', 'file' => $id])->assertOk();
    $import = Import::sole();

    expect($import->status)->toBe('uploaded')
        ->and($import->user_id)->toBe((string) $this->admin->id)
        ->and($import->original_name)->toBe('products.csv')
        ->and($import->model_class)->toBe(Product::class)
        ->and($import->file_disk)->toBe('local')
        ->and($import->file_name)->toMatch('#^lathe-imports/[0-9A-Z]{26}\.csv$#')
        ->and(Storage::disk('local')->get($import->file_name))->toBe(importFixture('products.csv'))
        ->and(PendingUpload::count())->toBe(0)
        ->and($response->json('result.effect'))->toBe([
            'type' => 'emit',
            'event' => 'import:headers-detected',
            'data' => [
                'import_id' => $import->id,
                'original_name' => 'products.csv',
                'headers' => ['Name', 'SKU', 'Price'],
                'preview' => [
                    ['Trail Mug', 'SKU-90001', '12.50'],
                    ['Camp Kettle', 'SKU-90002', '34.00'],
                    ['Updated Product One', 'SKU-00001', '19.99'],
                ],
                'columns' => [
                    ['name' => 'name', 'label' => 'Name', 'required' => true],
                    ['name' => 'sku', 'label' => 'Sku', 'required' => true],
                    ['name' => 'price', 'label' => 'Price', 'required' => true],
                ],
                'auto_mapping' => ['name', 'sku', 'price'],
            ],
        ]);
});

it('previews only the first 5 rows', function () {
    $rows = implode('', array_map(fn (int $i) => "Item {$i},SKU-7000{$i},{$i}.00\n", range(1, 8)));
    $id = sendImportFile('many.csv', "Name,SKU,Price\n".$rows);

    $preview = importStep(['import_step' => 'upload', 'file' => $id])->assertOk()->json('result.effect.data.preview');

    expect($preview)->toBe([
        ['Item 1', 'SKU-70001', '1.00'],
        ['Item 2', 'SKU-70002', '2.00'],
        ['Item 3', 'SKU-70003', '3.00'],
        ['Item 4', 'SKU-70004', '4.00'],
        ['Item 5', 'SKU-70005', '5.00'],
    ]);
});

it('answers a danger message for an empty file', function () {
    // An empty file cannot pass the upload protocol (its size is 0), so the complete upload is made here.
    $upload = PendingUpload::create([
        'user_id' => (string) $this->admin->id, 'field' => 'actions/import/file', 'disk' => 'local', 'path' => 'lathe-imports',
        'original_name' => 'empty.csv', 'size' => 1, 'status' => PendingUpload::COMPLETE,
    ]);
    Storage::disk('local')->put($upload->folder().'/assembled', '');

    $response = importStep(['import_step' => 'upload', 'file' => $upload->id])->assertOk();

    expect($response->json('result.message'))->toBe('The file is empty and has no headers.')
        ->and($response->json('result.level'))->toBe('danger')
        ->and(Import::count())->toBe(0);
});

it('auto-maps a misspelt header', function () {
    $id = sendImportFile('typo.csv', "Name,SKU,Prise\nTrail Mug,SKU-90001,12.50\n");

    expect(importStep(['import_step' => 'upload', 'file' => $id])->assertOk()->json('result.effect.data.auto_mapping'))
        ->toBe(['name', 'sku', 'price']);
});

/**
 * Upload products.csv through the steps and answer the Import row's id.
 */
function uploadedProductsImport(): int
{
    $id = sendImportFile('products.csv', importFixture('products.csv'));

    return (int) importStep(['import_step' => 'upload', 'file' => $id])->assertOk()->json('result.effect.data.import_id');
}

it('runs a dry run that writes nothing and reports its counts', function () {
    $importId = uploadedProductsImport();

    $response = importStep(['import_step' => 'dry_run', 'import_id' => $importId, 'column_map' => ['name', 'sku', 'price']])->assertOk();

    expect($response->json('import_run'))->toBe(['id' => $importId])
        ->and($response->json('result.message'))->toBe('Validation started.');

    $status = $this->getJson("/lathe/api/imports/{$importId}")->assertOk();

    expect($status->json('status'))->toBe('completed')
        ->and($status->json('mode'))->toBe('dry_run')
        ->and($status->json('created_rows'))->toBe(2)
        ->and($status->json('updated_rows'))->toBe(1)
        ->and($status->json('failed_rows'))->toBe(0)
        ->and(Import::findOrFail($importId)->column_map)->toBe(['name', 'sku', 'price'])
        ->and(Product::count())->toBe(500);
});

it('imports the file on the same row', function () {
    $importId = uploadedProductsImport();

    $response = importStep(['import_step' => 'import', 'import_id' => $importId, 'column_map' => ['name', 'sku', 'price']])->assertOk();

    expect($response->json('result.message'))->toBe('Import started.')
        ->and($response->json('import_run'))->toBe(['id' => $importId])
        ->and(Import::count())->toBe(1)
        ->and(Import::findOrFail($importId)->status)->toBe('completed')
        ->and(Product::count())->toBe(502)
        ->and(Product::where('sku', 'SKU-00001')->value('name'))->toBe('Updated Product One');
});

it('answers 404 for an import of another user', function () {
    Storage::disk('local')->put('lathe-imports/theirs.csv', importFixture('products.csv'));
    $theirs = Import::create([
        'user_id' => (string) $this->editor->id, 'file_disk' => 'local', 'file_name' => 'lathe-imports/theirs.csv',
        'original_name' => 'products.csv', 'model_class' => Product::class,
    ]);

    importStep(['import_step' => 'dry_run', 'import_id' => $theirs->id, 'column_map' => ['name', 'sku', 'price']])->assertNotFound();
    importStep(['import_step' => 'import', 'import_id' => $theirs->id, 'column_map' => ['name', 'sku', 'price']])->assertNotFound();

    expect(Product::count())->toBe(500)->and($theirs->fresh()?->status)->toBe('uploaded');
});
