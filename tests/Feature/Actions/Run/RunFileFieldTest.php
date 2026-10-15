<?php

namespace Lathe\Tests\Feature\Actions\Run;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class RunImportLabels extends Action
{
    /** @var list<mixed> */
    public static array $received = [];

    public function fields(LatheRequest $request): array
    {
        return [FileField::make('Labels')->required()];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        static::$received[] = $fields->get('labels');

        return ActionResult::message('Labels imported.');
    }
}

class RunLabelOrders extends Resource
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
        return [RunImportLabels::make()];
    }
}

/**
 * A multipart body, the selection as JSON text the way the browser sends it.
 *
 * @param  array<string, mixed>  $selection
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function runLabelsBody(array $selection, array $fields): array
{
    return ['action' => 'run-import-labels', 'selection' => json_encode($selection), 'fields' => $fields];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    RunImportLabels::$received = [];
    Lathe::registerResources(RunLabelOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('action_file_field_reaches_handler', function () {
    $file = UploadedFile::fake()->create('labels.csv', 2, 'text/csv');

    $this->post('/lathe/api/resources/run-label-orders/action', runLabelsBody(['mode' => 'ids', 'ids' => [1]], ['labels' => $file]), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('result.message', 'Labels imported.');

    expect(RunImportLabels::$received)->toHaveCount(1)
        ->and(RunImportLabels::$received[0])->toBeInstanceOf(UploadedFile::class)
        ->and(RunImportLabels::$received[0]->getClientOriginalName())->toBe('labels.csv');
})->group('regression');

it('action_event_stores_file_metadata_not_the_file', function () {
    $file = UploadedFile::fake()->create('labels.csv', 2, 'text/csv');

    $this->post('/lathe/api/resources/run-label-orders/action', runLabelsBody(['mode' => 'ids', 'ids' => [1]], ['labels' => $file]), ['Accept' => 'application/json'])
        ->assertOk();

    expect(ActionEvent::sole()->fields)->toMatchStoredJson(['labels' => ['name' => 'labels.csv', 'size' => 2048, 'mime' => 'text/csv']]);
})->group('regression');

it('answers 422 on the field key when the required file is missing', function () {
    $this->post('/lathe/api/resources/run-label-orders/action', runLabelsBody(['mode' => 'ids', 'ids' => [1]], []), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['labels']);

    expect(RunImportLabels::$received)->toBe([]);
});

it('refuses a file field action over 500 rows', function () {
    $file = UploadedFile::fake()->create('labels.csv', 2, 'text/csv');

    $this->post('/lathe/api/resources/run-label-orders/action', runLabelsBody(['mode' => 'all'], ['labels' => $file]), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('errors.selection', ['This action accepts at most 500 rows.']);

    expect(RunImportLabels::$received)->toBe([]);
});
