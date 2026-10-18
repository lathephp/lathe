<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Actions\BulkRun;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Import\Import;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The top-level keys of a JSON object, sorted.
 *
 * @return list<string>
 */
function jsonShape(mixed $json): array
{
    $keys = is_array($json) ? array_keys($json) : [];
    sort($keys);

    return array_map('strval', $keys);
}

// m4, m5 and m6 add their list endpoints to this dataset.
test('each_list_endpoint_has_its_documented_shape', function (string $uri, array $keys, ?string $item, array $itemKeys) {
    $json = $this->getJson($uri)->assertOk()->json();
    sort($keys);
    sort($itemKeys);

    expect(jsonShape($json))->toBe($keys);

    if ($item !== null) {
        expect(jsonShape(data_get($json, $item)))->toBe($itemKeys);
    }
})->with([
    // An index with an inline cell sends each row's stale-edit token.
    'resource index' => ['/lathe/api/resources/orders', ['resource', 'table', 'filters', 'tabs', 'groups', 'lens', 'lenses', 'state', 'actions'], 'table.rows.0', ['id', 'title', 'cells', 'abilities', 'updatedAt', 'actions']],
    'resource count' => ['/lathe/api/resources/orders/count', ['value', 'exact', 'capped', 'estimated'], null, []],
    'filters' => ['/lathe/api/resources/orders/filters', ['filters'], 'filters.0', ['key', 'name', 'component', 'options', 'remote', 'immediate', 'placement', 'default']],
    'filter options' => ['/lathe/api/resources/orders/filters/customer/options?q=a', ['options', 'hasMore'], 'options.0', ['label', 'value']],
    'saved tabs' => ['/lathe/api/resources/orders/saved-tabs', ['tabs'], null, []],
    'lens index' => ['/lathe/api/resources/orders/lenses/pending-orders', ['resource', 'table', 'filters', 'tabs', 'groups', 'lens', 'lenses', 'state', 'actions'], 'table.rows.0', ['id', 'title', 'cells', 'abilities', 'actions']],
    'lens count' => ['/lathe/api/resources/orders/lenses/pending-orders/count', ['value', 'exact', 'capped', 'estimated'], null, []],
    'lens filters' => ['/lathe/api/resources/orders/lenses/pending-orders/filters', ['filters'], 'filters.0', ['key', 'name', 'component', 'options', 'remote', 'immediate', 'placement', 'default']],
    'relation index' => ['/lathe/api/resources/orders/12/items', ['columns', 'sortKeys', 'rows', 'count', 'page', 'perPage', 'perPageOptions', 'hasMore', 'searchable', 'pivotActions'], 'rows.0', ['id', 'title', 'cells', 'abilities', 'related_id']],
    'global search' => ['/lathe/api/search?q=ann', ['groups', 'skipped'], 'groups.0.results.0', ['resourceTitle', 'title', 'subTitle', 'resourceName', 'resourceId', 'avatar', 'rounded', 'url']],
    'notifications' => ['/lathe/api/notifications', ['notifications', 'unreadCount'], null, []],
    'actions' => ['/lathe/api/resources/orders/actions?display=index', ['actions', 'pivotActions', 'counts'], 'counts', ['standalone', 'sole', 'resource']],
    'action form' => ['/lathe/api/resources/orders/actions/mark-shipped/form?count=3', ['fields', 'confirmText', 'action'], null, []],
    'dashboards' => ['/lathe/api/dashboards', ['dashboards'], 'dashboards.0', ['uriKey', 'name', 'description', 'icon', 'showRefreshButton', 'customizable', 'ranges']],
    'dashboard widgets' => ['/lathe/api/dashboards/main/widgets', ['widgets'], null, []],
    'resource widgets' => ['/lathe/api/resources/orders/widgets', ['widgets'], null, []],
    'lens widgets' => ['/lathe/api/resources/orders/lenses/pending-orders/widgets', ['widgets'], null, []],
    'plugin settings' => ['/lathe/api/plugins/hello-plugin/settings', ['fields', 'layout', 'values'], null, []],
])->group('regression');

// The status endpoints m5 adds answer for the run's owner, so each needs a run first.
test('each_run_status_endpoint_has_its_documented_shape', function () {
    $userId = User::where('email', 'admin@lathephp.com')->value('id');
    $bulk = BulkRun::create(['user_id' => $userId, 'resource_class' => 'Workbench\\App\\Lathe\\Resources\\Order', 'action_name' => 'Mark Shipped', 'status' => 'running', 'total' => 3]);
    $export = Export::create(['user_id' => $userId, 'file_disk' => 'local']);
    $import = Import::create(['user_id' => $userId, 'file_name' => 'a.csv', 'model_class' => Product::class]);

    expect(jsonShape($this->getJson("/lathe/api/bulk-runs/{$bulk->id}")->assertOk()->json()))->toBe(['failed', 'processed', 'skipped', 'status', 'total'])
        ->and(jsonShape($this->getJson("/lathe/api/exports/{$export->id}")->assertOk()->json()))->toBe(['download_url', 'exception', 'id', 'processed_rows', 'status', 'total_rows'])
        ->and(jsonShape($this->getJson("/lathe/api/imports/{$import->id}")->assertOk()->json()))->toBe(['created_rows', 'errors_url', 'exception', 'failed_rows', 'first_errors', 'id', 'mode', 'processed_rows', 'resource_label', 'skipped_rows', 'status', 'total_rows', 'updated_rows'])
        ->and(jsonShape($this->getJson('/lathe/api/resources/action-events')->assertOk()->json()))->toContain('table', 'resource');
})->group('regression');
