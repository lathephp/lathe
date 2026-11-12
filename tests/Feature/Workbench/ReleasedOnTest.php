<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Import\Import;
use Lathe\Support\JobContext;
use Lathe\Testing\LatheTest;
use Workbench\App\Lathe\Actions\ImportProducts;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
});

it('lists the date-only Released column on Products', function () {
    Product::query()->update(['released_on' => '2026-03-08']);
    LatheTest::actingAs($this->admin);

    expect(LatheTest::resource('products')->index()->assertOk()->json('table.rows.0.cells.released_on'))
        ->toBe(['value' => '2026-03-08', 'display' => '2026-03-08']);
});

it('imports a Released cell as its calendar day for a user in Tokyo', function () {
    Storage::fake('local');
    $file = 'lathe-imports/'.Str::ulid().'.csv';
    Storage::disk('local')->put($file, "Name,SKU,Price,Released\nTrail Mug,SKU-90001,12.50,2026-03-08\n");
    $import = Import::create([
        'user_id' => (string) $this->admin->id,
        'file_disk' => 'local',
        'file_name' => $file,
        'original_name' => 'products.csv',
        'model_class' => Product::class,
        'column_map' => [0 => 'name', 1 => 'sku', 2 => 'price', 3 => 'released_on'],
    ]);

    ImportProducts::make()->start($import, 'import', new JobContext($this->admin->id, 'web', 'en', 'Asia/Tokyo'));

    expect(Product::where('sku', 'SKU-90001')->firstOrFail()->getAttribute('released_on')?->toDateString())->toBe('2026-03-08');
});

it('runs the app on the zone BENCH_APP_TIMEZONE names, and on UTC without it', function (?string $zone, string $expected) {
    putenv($zone === null ? 'BENCH_APP_TIMEZONE' : "BENCH_APP_TIMEZONE={$zone}");
    $before = date_default_timezone_get();

    try {
        (new WorkbenchServiceProvider(app()))->register();
        $set = [config('app.timezone'), date_default_timezone_get()];
    } finally {
        putenv('BENCH_APP_TIMEZONE');
        date_default_timezone_set($before);
    }

    expect($set)->toBe([$expected, $expected]);
})->with([['Asia/Dhaka', 'Asia/Dhaka'], [null, 'UTC']]);

it('serves the workbench on the zone BENCH_APP_TIMEZONE names', function () {
    $shown = Process::timeout(120)->path(dirname(__DIR__, 3))->env(['BENCH_APP_TIMEZONE' => 'Asia/Dhaka'])
        ->run(['php', 'vendor/bin/testbench', 'config:show', 'app.timezone']);

    expect($shown->output())->toContain('Asia/Dhaka');
});
