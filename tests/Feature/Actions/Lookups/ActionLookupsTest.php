<?php

namespace Lathe\Tests\Feature\Actions\Lookups;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Field\FieldPatch;
use Lathe\Field\FormData;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class LookupShip extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [
            Field::select('Carrier')->options(['ups' => 'UPS', 'dhl' => 'DHL']),
            Field::text('Tracking')->dependsOn(['carrier'], function (FieldPatch $field, FormData $data) {
                $field->setValue(strtoupper((string) $data->get('carrier')).'-');
            }),
        ];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        return ActionResult::message('Shipped.');
    }
}

class LookupOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function actions(LatheRequest $request): array
    {
        return [LookupShip::make(), LookupShip::make()->withUriKey('hidden-ship')->canSee(fn () => false)];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(LookupOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('answers a dependent-field lookup of an action modal as the form lookup does', function () {
    LatheTest::resource('lookup-orders')->actionDependencies('lookup-ship', 'carrier', ['carrier' => 'ups'])
        ->assertOk()
        ->assertJsonPath('changed.tracking', 'UPS-')
        ->assertJsonStructure(['patches' => ['tracking'], 'changed']);
});

it('serves the picker of an action relation field, capped at the picker size', function () {
    Tag::query()->insert(array_map(fn (int $i) => ['name' => "Bulk {$i}", 'created_at' => now(), 'updated_at' => now()], range(1, 40)));

    $json = LatheTest::resource('tags')->actionAssociatable('merge-tags', 'target', ['per_page' => 100])->assertOk()->json();

    expect($json['items'])->toHaveCount(25)
        ->and($json['has_more'])->toBeTrue()
        ->and(array_keys($json['items'][0]))->toContain('value', 'display');
});

it('answers 404 for an unknown action or field and 403 for a hidden action', function () {
    LatheTest::resource('tags')->actionAssociatable('nope', 'target')->assertNotFound();
    LatheTest::resource('tags')->actionAssociatable('merge-tags', 'nope')->assertNotFound();
    LatheTest::resource('lookup-orders')->actionDependencies('hidden-ship', 'carrier')->assertForbidden();
});

it('merges the selected tags into the target', function () {
    [$sale, $new, $target] = Tag::query()->orderBy('id')->limit(3)->get()->all();
    $product = Product::findOrFail(1);
    $product->tags()->sync([$sale->id => ['position' => 1]]);
    Product::findOrFail(2)->tags()->sync([$new->id => ['position' => 2], $target->id => ['position' => 3]]);

    LatheTest::resource('tags')->runAction('merge-tags', ['mode' => 'ids', 'ids' => [$sale->id, $new->id]], ['target' => $target->id])
        ->assertOk()
        ->assertJsonPath('result.message', 'Tags merged.');

    expect(Tag::whereKey([$sale->id, $new->id])->count())->toBe(0)
        ->and($product->tags()->pluck('tags.id')->all())->toBe([$target->id])
        ->and(Product::findOrFail(2)->tags()->pluck('tags.id')->all())->toBe([$target->id])
        ->and(DB::table('taggables')->whereIn('tag_id', [$sale->id, $new->id])->exists())->toBeFalse();
});

it('merges into the target the modal picker sends as {value, display}', function () {
    [$sale, $new, $target] = Tag::query()->orderBy('id')->limit(3)->get()->all();

    LatheTest::resource('tags')->runAction('merge-tags', ['mode' => 'ids', 'ids' => [$sale->id, $new->id]], ['target' => ['value' => $target->id, 'display' => $target->name]])
        ->assertOk()
        ->assertJsonPath('result.message', 'Tags merged.');

    expect(Tag::whereKey([$sale->id, $new->id])->count())->toBe(0)
        ->and(Tag::whereKey($target->id)->exists())->toBeTrue();
});

test('lens_action_uses_the_same_dispatcher', function () {
    $pending = Order::where('status', 'pending')->orderBy('id')->limit(3)->pluck('id')->all();
    $other = Order::where('status', '!=', 'pending')->value('id');
    $selection = ['mode' => 'ids', 'ids' => [...$pending, $other]];

    // The index route reading through the lens, rolled back so the lens route sees the same rows.
    DB::beginTransaction();
    $index = LatheTest::resource('orders')->postAction('mark-shipped', $selection + ['lens' => 'pending-orders'])->assertOk()->json();
    DB::rollBack();

    $lens = LatheTest::resource('orders')->forLens('pending-orders')->postAction('mark-shipped', $selection)->assertOk()->json();

    expect($lens)->toBe($index)
        ->and($lens['permitted'])->toBe(3)
        ->and($lens['skipped'])->toBe(1)
        ->and(Order::whereKey($pending)->where('status', 'shipped')->count())->toBe(3);
})->group('regression');

it('serves the lookups of an action through a lens the same way', function () {
    $index = LatheTest::resource('orders')->actionAssociatable('mark-shipped', 'nope');
    $lens = LatheTest::resource('orders')->forLens('pending-orders')->actionAssociatable('mark-shipped', 'nope');

    expect($lens->status())->toBe($index->status())->toBe(404);
    LatheTest::resource('orders')->forLens('nope')->actionAssociatable('mark-shipped', 'nope')->assertNotFound();
});
