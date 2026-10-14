<?php

namespace Lathe\Tests\Feature\Actions\ReviewFixes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class LimitedImport extends Action
{
    /** @var list<string> */
    public static array $received = [];

    public function fields(LatheRequest $request): array
    {
        return [FileField::make('Sheet')->required()->maxSize(1)->acceptedTypes('csv')];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        $file = $fields->get('sheet');
        static::$received[] = $file instanceof UploadedFile ? $file->getClientOriginalName() : '';

        return ActionResult::message('Imported.');
    }
}

class LimitedOrders extends Resource
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
        return [LimitedImport::make()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    LimitedImport::$received = [];
    Lathe::registerResources(LimitedOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('refuses an action file over the field max size', function () {
    LatheTest::resource('limited-orders')->runAction('limited-import', ['mode' => 'ids', 'ids' => [1]], ['sheet' => UploadedFile::fake()->create('big.csv', 3072, 'text/csv')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sheet']);

    expect(LimitedImport::$received)->toBe([]);
});

it('refuses an action file of a type the field does not accept', function () {
    LatheTest::resource('limited-orders')->runAction('limited-import', ['mode' => 'ids', 'ids' => [1]], ['sheet' => UploadedFile::fake()->create('evil.php', 2, 'application/x-php')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sheet' => 'The file type is not one of: csv.']);

    expect(LimitedImport::$received)->toBe([]);
});

it('passes an action file within the field limits', function () {
    LatheTest::resource('limited-orders')->runAction('limited-import', ['mode' => 'ids', 'ids' => [1]], ['sheet' => UploadedFile::fake()->create('rows.csv', 2, 'text/csv')])
        ->assertOk()
        ->assertJsonPath('result.message', 'Imported.');

    expect(LimitedImport::$received)->toBe(['rows.csv']);
});
