<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

/**
 * The panel's JS tests build index cells from this file, so a cell there reads the way the
 * server really sends it. Set LATHE_WRITE_FIXTURES=1 to write it again after a payload change.
 */
it('matches the committed index snapshot the JS tests read', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-01-15 09:30:00', 'UTC'));
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(UserFactory::new()->create(['name' => 'Admin User', 'email' => 'admin@lathephp.com']));

    $ada = Customer::forceCreate(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'country' => 'GB']);
    $grace = Customer::forceCreate(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'country' => 'US']);
    Order::forceCreate(['customer_id' => $ada->id, 'number' => 'ORD-00000001', 'status' => 'pending', 'total_cents' => 1250, 'placed_at' => '2026-01-10 14:00:00']);
    Order::forceCreate(['customer_id' => $grace->id, 'number' => 'ORD-00000002', 'status' => 'shipped', 'total_cents' => 49900, 'placed_at' => '2026-01-12 08:15:00']);
    Post::forceCreate(['title' => 'Hello world', 'status' => 'draft', 'body' => '<p>Hello</p>']);
    Post::forceCreate(['title' => 'Release notes', 'status' => 'published', 'body' => '<p>Notes</p>']);

    $snapshot = [];

    foreach (['posts', 'orders', 'customers'] as $uriKey) {
        $table = $this->getJson("/lathe/api/resources/{$uriKey}")->assertOk()->json('table');
        // Only the table, and each row's keys the page draws: widgets, cards and the action list
        // change with the workbench, and no cell test reads them.
        $snapshot[$uriKey] = [
            'columns' => $table['columns'],
            'titleColumn' => $table['titleColumn'],
            'rowKey' => $table['rowKey'],
            'rows' => array_map(fn (array $row) => array_intersect_key($row, array_flip(['id', 'title', 'cells', 'abilities', 'updatedAt', 'trashed'])), $table['rows']),
        ];
    }

    $live = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    $path = dirname(__DIR__, 3).'/resources/js/__tests__/fixtures/index-payload.json';

    if (getenv('LATHE_WRITE_FIXTURES') === '1') {
        file_put_contents($path, $live);
    }

    expect(is_file($path))->toBeTrue("No index snapshot at {$path}. Run this test with LATHE_WRITE_FIXTURES=1 to write it.")
        ->and($live)->toBe((string) file_get_contents($path), 'The index payload no longer matches the snapshot. If the change is meant, run this test with LATHE_WRITE_FIXTURES=1 and commit the file.');
});
