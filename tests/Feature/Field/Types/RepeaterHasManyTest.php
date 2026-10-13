<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Lathe\Uploads\PendingUpload;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class HasManyBox extends Model
{
    protected $table = 'hm_boxes';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['gallery' => 'array'];
    }

    /**
     * @return HasMany<HasManyLine, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HasManyLine::class, 'box_id');
    }
}

class HasManyLine extends Model
{
    protected $table = 'hm_lines';

    protected $guarded = [];
}

class HasManyBoxes extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(HasManyBox::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name'),
            Field::repeater('Items')->fields([
                Field::text('Sku'),
                Field::text('Name'),
                Field::file('Photo')->disk('public'),
            ])->asHasMany('items', 'sku', 'position'),
            Field::repeater('Gallery')->fields([Field::text('Caption'), Field::file('Photo')->disk('public')]),
        ];
    }
}

class HasManyOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::repeater('Items')->fields([
                Field::number('Product Id'),
                Field::number('Quantity'),
                Field::number('Price Cents'),
            ])->asHasMany('items'),
        ];
    }
}

function hmBox(int $lines = 0, string $name = 'Box'): HasManyBox
{
    $box = HasManyBox::query()->create(['name' => $name]);

    for ($index = 0; $index < $lines; $index++) {
        $box->items()->create(['sku' => "{$name}-{$index}", 'name' => "Line {$index}", 'position' => $index]);
    }

    return $box;
}

/**
 * The lines of a box as [id, sku, name, position], in position order.
 *
 * @return list<array{int, string, string, int}>
 */
function hmLines(HasManyBox $box): array
{
    return $box->items()->orderBy('position')->get()
        ->map(fn (HasManyLine $line) => [(int) $line->id, (string) $line->sku, (string) $line->name, (int) $line->position])
        ->all();
}

function hmPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/** A finished upload of a PNG for the photo sub-field. */
function hmUpload(): PendingUpload
{
    $upload = PendingUpload::create([
        'user_id' => (string) User::where('email', 'admin@lathephp.com')->firstOrFail()->getKey(),
        'field' => 'photo',
        'disk' => 'public',
        'path' => '',
        'original_name' => 'shot.png',
        'size' => strlen(hmPng()),
        'status' => PendingUpload::COMPLETE,
    ]);

    Storage::disk('local')->put($upload->folder().'/assembled', hmPng());

    return $upload;
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('hm_lines');
    Schema::dropIfExists('hm_boxes');
    Schema::create('hm_boxes', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->json('gallery')->nullable();
    });
    Schema::create('hm_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('box_id');
        $table->string('sku');
        $table->string('name')->nullable();
        $table->integer('position')->default(0);
        $table->string('photo')->nullable();
        $table->timestamps();
    });
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(HasManyBoxes::class, HasManyOrders::class);
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('loads the children ordered by the order column with their keys', function () {
    $box = hmBox(2);
    [$first, $second] = hmLines($box);
    $box->items()->whereKey($first[0])->update(['position' => 5]);

    $form = LatheTest::resource('has-many-boxes')->updateFields($box->id)->assertOk();

    expect(collect($form->json('fields'))->firstWhere('attribute', 'items')['value'])->toBe([
        ['sku' => 'Box-1', 'name' => 'Line 1', 'photo' => null, '_key' => $second[0]],
        ['sku' => 'Box-0', 'name' => 'Line 0', 'photo' => null, '_key' => $first[0]],
    ]);
});

it('updates keyed rows, upserts by the unique field, creates the rest, orders and deletes', function () {
    $box = hmBox(3);
    [$a, $b, $c] = hmLines($box);

    LatheTest::resource('has-many-boxes')->update($box->id, ['items' => [
        ['_key' => $c[0], 'sku' => 'Box-2', 'name' => 'Third first'],
        ['sku' => 'Box-0', 'name' => 'Matched by sku'],
        ['sku' => 'NEW', 'name' => 'Brand new'],
    ]])->assertOk();

    $lines = hmLines($box);

    expect($lines[0])->toBe([$c[0], 'Box-2', 'Third first', 0])
        ->and($lines[1])->toBe([$a[0], 'Box-0', 'Matched by sku', 1])
        ->and(array_slice($lines[2], 1))->toBe(['NEW', 'Brand new', 2])
        ->and($lines[2][0])->not->toBeIn([$a[0], $b[0], $c[0]])
        ->and(HasManyLine::query()->whereKey($b[0])->exists())->toBeFalse();
});

it('creates a new row for a key of another parent and leaves the foreign row alone', function () {
    $mine = hmBox(1, 'Mine');
    $other = hmBox(1, 'Other');
    [$foreign] = hmLines($other);

    LatheTest::resource('has-many-boxes')->update($mine->id, ['items' => [
        ['_key' => $foreign[0], 'sku' => 'Stolen', 'name' => 'Taken'],
    ]])->assertOk();

    $lines = hmLines($mine);

    expect($lines)->toHaveCount(1)
        ->and(array_slice($lines[0], 1))->toBe(['Stolen', 'Taken', 0])
        ->and($lines[0][0])->not->toBe($foreign[0])
        ->and(hmLines($other))->toBe([$foreign]);
});

it('repeater_has_many_sync_is_one_pass', function () {
    $count = function (int $rows): int {
        $box = hmBox($rows, "B{$rows}");
        $lines = hmLines($box);
        // Keep all but the first, reversed, and add one new row: an update, a create and a delete.
        $submitted = array_map(fn (array $line) => ['_key' => $line[0], 'sku' => $line[1], 'name' => 'Kept'], array_reverse(array_slice($lines, 1)));
        $submitted[] = ['sku' => "B{$rows}-new", 'name' => 'New'];

        DB::flushQueryLog();
        DB::enableQueryLog();
        LatheTest::resource('has-many-boxes')->update($box->id, ['items' => $submitted])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect(hmLines($box))->toHaveCount($rows);

        return $queries;
    };

    expect($count(5))->toBe($count(50));
})->group('regression');

it('keeps the ids of the order items left after one row is deleted', function () {
    $order = Order::query()->firstOrFail();
    $order->items()->delete();
    $product = Product::query()->firstOrFail();
    $items = collect([1, 2, 3])->map(fn (int $quantity) => $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'price_cents' => 100 * $quantity]));

    $form = LatheTest::resource('has-many-orders')->updateFields($order->id)->assertOk();
    $rows = collect($form->json('fields'))->firstWhere('attribute', 'items')['value'];

    expect($rows)->toHaveCount(3);

    LatheTest::resource('has-many-orders')->update($order->id, ['items' => [$rows[0], $rows[2]]])->assertOk();

    expect($order->items()->orderBy('id')->pluck('id')->all())->toBe([$items[0]->id, $items[2]->id])
        ->and(OrderItem::query()->whereKey($items[1]->id)->exists())->toBeFalse();
});

it('starts an upload for a file sub-field by its scoped key', function () {
    $box = hmBox(1);

    $this->postJson('/lathe/api/resources/has-many-boxes/uploads/items.0.photo', ['name' => 'a.png', 'size' => 10, 'mime' => 'image/png', 'resource_id' => $box->id])
        ->assertCreated();
    $this->postJson('/lathe/api/resources/has-many-boxes/uploads/gallery.3.photo', ['name' => 'a.png', 'size' => 10, 'mime' => 'image/png'])
        ->assertCreated();
    $this->postJson('/lathe/api/resources/has-many-boxes/uploads/gallery.0.caption', ['name' => 'a.png', 'size' => 10, 'mime' => 'image/png'])
        ->assertNotFound();

    expect(PendingUpload::query()->pluck('field')->all())->toBe(['photo', 'photo']);
});

it('stores a file sub-field into a JSON row and keeps it on the next save', function () {
    $box = hmBox();
    $upload = hmUpload();

    LatheTest::resource('has-many-boxes')->update($box->id, ['gallery' => [['caption' => 'Front', 'photo' => $upload->id]]])->assertOk();

    $path = $box->refresh()->gallery[0]['photo'];

    expect($path)->toBeString()->toEndWith('.png')
        ->and(Storage::disk('public')->get($path))->toBe(hmPng())
        ->and(PendingUpload::query()->whereKey($upload->id)->exists())->toBeFalse();

    $form = LatheTest::resource('has-many-boxes')->updateFields($box->id)->assertOk();
    $rows = collect($form->json('fields'))->firstWhere('attribute', 'gallery')['value'];

    LatheTest::resource('has-many-boxes')->update($box->id, ['gallery' => [
        ['caption' => 'Back', 'photo' => ['path' => 'forged.png']],
        [...$rows[0], 'caption' => 'Front again'],
    ]])->assertOk();

    expect($box->refresh()->gallery)->toBe([
        ['caption' => 'Back', 'photo' => null],
        ['caption' => 'Front again', 'photo' => $path],
    ]);
});

it('stores a file sub-field of a hasMany row on the child', function () {
    $box = hmBox();
    $upload = hmUpload();

    LatheTest::resource('has-many-boxes')->update($box->id, ['items' => [['sku' => 'P', 'name' => 'Pic', 'photo' => $upload->id]]])->assertOk();

    $photo = $box->items()->firstOrFail()->photo;

    expect($photo)->toBeString()
        ->and(Storage::disk('public')->get((string) $photo))->toBe(hmPng());
});
