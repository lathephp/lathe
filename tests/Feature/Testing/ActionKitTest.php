<?php

namespace Lathe\Tests\Feature\Testing;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class KitAttachManifest extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [FileField::make('Manifest'), Field::text('Note')];
    }

    /**
     * Answers what reached it, so the test sees how the helper sent the form.
     */
    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        $file = $fields->get('manifest');
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : 'no file';

        return ActionResult::message($name.' | '.$fields->get('note'));
    }
}

class KitOrders extends Resource
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
        return [KitAttachManifest::make()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(KitOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

test('multipart_action_helper_sends_files', function () {
    LatheTest::resource('kit-orders')->runAction('kit-attach-manifest', ['mode' => 'ids', 'ids' => [1]], [
        'manifest' => UploadedFile::fake()->create('manifest.pdf', 10),
        'note' => 'Fragile',
    ])->assertOk()->assertJsonPath('result.message', 'manifest.pdf | Fragile');
})->group('regression');

it('runs an action as JSON when no field holds a file', function () {
    LatheTest::resource('kit-orders')->runAction('kit-attach-manifest', ['mode' => 'ids', 'ids' => [1]], ['note' => 'Today'])
        ->assertOk()
        ->assertJsonPath('result.message', 'no file | Today');
});

it('sends the lens twins through the lens routes', function () {
    $tester = LatheTest::resource('orders')->forLens('pending-orders');

    $tester->actions()->assertOk()->assertJsonPath('actions.2.uriKey', 'mark-shipped');
    $tester->actionForm('mark-shipped', ['count' => 2])->assertOk()->assertJsonPath('confirmText', 'Mark 2 orders as shipped?');
});
