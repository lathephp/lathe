<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Import\FailedImportRow;
use Lathe\Actions\Import\Import;
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Support\JobContext;
use Workbench\App\Lathe\Actions\ImportProducts;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Products whose SKU starts HIDDEN- are outside the edit scope; with no user, every row is. */
class ScopedImportProducts extends Resource
{
    public static function uriKey(): string
    {
        return 'scoped-import-products';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Product::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $request->user() === null ? $query->whereRaw('1 = 0') : $query->where('sku', 'not like', 'HIDDEN-%');
    }
}

/** The customers table, read through ScopedImportCustomers. */
class ScopedImportCustomer extends Customer
{
    protected $table = 'customers';
}

/** The orders table, whose customer is a ScopedImportCustomer. */
class ScopedImportOrder extends Order
{
    protected $table = 'orders';

    /**
     * @return BelongsTo<ScopedImportCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(ScopedImportCustomer::class, 'customer_id');
    }
}

/** Customers whose email starts hidden. are not offered to a relation. */
class ScopedImportCustomers extends Resource
{
    public static function uriKey(): string
    {
        return 'scoped-import-customers';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ScopedImportCustomer::class)->title('name')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('email', 'not like', 'hidden.%');
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Storage::fake('local');
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    Lathe::registerResources(ScopedImportProducts::class, ScopedImportCustomers::class);
    Product::where('sku', 'SKU-00002')->update(['sku' => 'HIDDEN-1', 'name' => 'Hidden one']);
});

/**
 * @param  array<int, string>  $map  header index => column name
 * @param  class-string  $model
 */
function scopedUpload(string $csv, array $map, string $model = Product::class): Import
{
    $file = 'lathe-imports/'.Str::ulid().'.csv';
    Storage::disk('local')->put($file, $csv);

    return Import::create([
        'user_id' => (string) test()->admin->id,
        'file_disk' => 'local',
        'file_name' => $file,
        'original_name' => 'products.csv',
        'model_class' => $model,
        'column_map' => $map,
    ]);
}

/**
 * @return array{created_rows: int, updated_rows: int, skipped_rows: int, failed_rows: int}
 */
function scopedCounts(Import $import): array
{
    $import->refresh();

    return [
        'created_rows' => $import->created_rows,
        'updated_rows' => $import->updated_rows,
        'skipped_rows' => $import->skipped_rows,
        'failed_rows' => $import->failed_rows,
    ];
}

function scopedContext(): JobContext
{
    return new JobContext(test()->admin->id, 'web', 'en', 'UTC');
}

it('never updates a row outside the edit scope, in a dry run or a real one', function (string $mode) {
    $import = scopedUpload("Name,SKU,Price\nRenamed,HIDDEN-1,9.50\nInside,SKU-00001,8.50\n", [0 => 'name', 1 => 'sku', 2 => 'price']);

    ImportProducts::make()->forResource(ScopedImportProducts::class)->start($import, $mode, scopedContext());

    $counts = scopedCounts($import);

    expect($counts['updated_rows'])->toBe(1)
        ->and($counts['created_rows'] + $counts['failed_rows'])->toBe(1)
        ->and(Product::where('sku', 'HIDDEN-1')->value('name'))->toBe('Hidden one')
        ->and(Product::where('sku', 'SKU-00001')->value('name'))->toBe($mode === 'import' ? 'Inside' : Product::where('sku', 'SKU-00001')->value('name'));
})->with(['dry_run', 'import']);

it('does not count a key held only outside the edit scope as skipped', function () {
    $import = scopedUpload("Name,SKU\nRenamed,HIDDEN-1\nInside,SKU-00001\n", [0 => 'name', 1 => 'sku']);

    ImportAction::make()->forResource(ScopedImportProducts::class)
        ->withColumns(ImportColumn::make('name'), ImportColumn::make('sku'))
        ->skipDuplicates('sku')
        ->start($import, 'dry_run', scopedContext());

    expect(scopedCounts($import))->toBe(['created_rows' => 1, 'updated_rows' => 0, 'skipped_rows' => 1, 'failed_rows' => 0]);
});

it('resolves a relationship value outside the related relatable scope as an unknown value', function () {
    $hidden = Customer::orderBy('id')->firstOrFail();
    $hidden->update(['email' => 'hidden.'.$hidden->email]);
    $shown = Customer::where('id', '!=', $hidden->id)->orderBy('id')->firstOrFail();
    $import = scopedUpload("Number,Customer\nN1,{$hidden->email}\nN2,{$shown->email}\n", [0 => 'number', 1 => 'customer_id'], ScopedImportOrder::class);

    ImportAction::make()->withModel(ScopedImportOrder::class)
        ->withColumns(ImportColumn::make('number'), ImportColumn::make('customer_id')->relationship('customer', 'email')->rules('required'))
        ->start($import, 'dry_run', scopedContext());

    expect(scopedCounts($import))->toBe(['created_rows' => 1, 'updated_rows' => 0, 'skipped_rows' => 0, 'failed_rows' => 1])
        ->and(FailedImportRow::where('import_id', $import->id)->value('errors'))->toBe(['customer_id' => ['The customer id field is required.']]);
});

it('looks up each chunk\'s own keys with one query per key column', function () {
    config(['lathe.imports.chunk_size' => 500]);
    $lines = ['Name,SKU,Price'];

    for ($i = 1; $i <= 1200; $i++) {
        $lines[] = sprintf('Product %d,%s,1.00', $i, $i === 1100 ? 'SKU-00001' : sprintf('SKU-B%04d', $i));
    }

    $import = scopedUpload(implode("\n", $lines)."\n", [0 => 'name', 1 => 'sku', 2 => 'price']);
    $lookups = 0;
    DB::listen(function (QueryExecuted $query) use (&$lookups) {
        $lookups += preg_match('/from ["`]?products["`]? where .*["`]?sku["`]? in/i', $query->sql) === 1 ? 1 : 0;
    });

    ImportProducts::make()->forResource(ScopedImportProducts::class)->start($import, 'dry_run', scopedContext());

    expect($lookups)->toBe(3)
        ->and(scopedCounts($import))->toBe(['created_rows' => 1199, 'updated_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0]);
});
