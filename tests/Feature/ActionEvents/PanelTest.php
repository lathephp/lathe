<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Lathe\ActionEvents\ActionEvent;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class PanelTestLogHidden
{
    public function viewAny(User $user): bool
    {
        return $user->email !== 'editor@lathephp.com';
    }

    public function view(User $user, ActionEvent $event): bool
    {
        return $this->viewAny($user);
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

afterEach(function () {
    Model::preventLazyLoading(false);
});

function panelEvents(int $count, int $order = 12): void
{
    for ($i = 0; $i < $count; $i++) {
        ActionEvent::query()->forceCreate([
            'batch_id' => (string) Str::uuid(),
            'type' => 'update',
            'status' => 'finished',
            'user_id' => '1',
            'actionable_type' => Order::class,
            'actionable_id' => (string) $order,
            'changes' => ['status' => 'paid'],
        ]);
    }
}

/**
 * The record prop of an order's detail page.
 *
 * @return array<string, mixed>
 */
function panelRecord(): array
{
    $record = [];
    test()->get('/lathe/resources/orders/12')->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$record) {
            $record = $page->toArray()['props']['record'];
        });

    return $record;
}

/**
 * @return list<string>
 */
function panelQueries(Closure $request): array
{
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });
    $request();

    return $queries;
}

it('puts a folded Action Events panel with no rows on the order detail page', function () {
    panelEvents(2);

    $record = panelRecord();
    $field = collect($record['fields'])->firstWhere('key', 'actions');

    expect($field['label'])->toBe('Action Events')
        ->and($field['collapsible'])->toBeTrue()
        ->and($field['collapsed'])->toBeTrue()
        ->and($field['value'])->toBeNull()
        ->and(json_encode($record['layout']))->toContain('"attribute":"actions"');
});

it('lists the order\'s events from the actions sub-resource endpoint', function () {
    panelEvents(2);
    panelEvents(1, 13);

    $json = $this->getJson('/lathe/api/resources/orders/12/actions')->assertOk()->json();

    expect($json['rows'])->toHaveCount(2)
        ->and($json['rows'][0]['cells']['changes']['value'])->toBe(['status' => 'paid']);
});

it('hides the panel from a viewer who may not view the log', function () {
    Gate::policy(ActionEvent::class, PanelTestLogHidden::class);
    panelEvents(1);
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $keys = collect(panelRecord()['fields'])->pluck('key');
    $this->getJson('/lathe/api/resources/orders/12/actions')->assertForbidden();

    expect($keys)->not->toContain('actions');
});

it('reads the detail page with the same queries for 0 and for 5 events', function () {
    warmNavigationBadges();
    $without = panelQueries(fn () => panelRecord());
    panelEvents(5);
    $with = panelQueries(fn () => panelRecord());

    expect($with)->toHaveCount(count($without));
});

it('reads the panel rows with the same queries for 1 and for 5 events', function () {
    panelEvents(1);
    Model::preventLazyLoading();
    $one = panelQueries(fn () => $this->getJson('/lathe/api/resources/orders/12/actions')->assertOk());
    panelEvents(4);
    $five = panelQueries(fn () => $this->getJson('/lathe/api/resources/orders/12/actions')->assertOk());

    expect($five)->toHaveCount(count($one));
});
