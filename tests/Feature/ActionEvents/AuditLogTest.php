<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Lathe\ActionEvents\ActionEvent;
use Lathe\ActionEvents\ActionEventPolicy;
use Lathe\Lathe;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** A model no Resource covers, over the orders table so its row exists. */
class AuditLogUnlistedThing extends Model
{
    protected $table = 'orders';
}

class AuditLogTagsNobodyOpens
{
    public function view(User $user, Tag $tag): bool
    {
        return false;
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * @param  array<string, mixed>  $attributes
 */
function auditEvent(array $attributes = []): ActionEvent
{
    return ActionEvent::query()->forceCreate([
        'batch_id' => (string) Str::uuid(),
        'type' => 'update',
        'status' => 'finished',
        'actionable_type' => Order::class,
        'actionable_id' => '12',
        ...$attributes,
    ]);
}

it('answers 403 to every request to the log under the policy as shipped', function () {
    Gate::policy(ActionEvent::class, ActionEventPolicy::class);
    $event = auditEvent();

    $this->getJson('/lathe/api/resources/action-events')->assertForbidden();
    $this->getJson("/lathe/api/resources/action-events/{$event->id}/preview")->assertForbidden();
    $this->get('/lathe/resources/action-events')->assertForbidden();
});

it('lists the rows to the admin under the workbench policy and writes none by viewing', function () {
    auditEvent();
    auditEvent(['type' => 'create']);

    $json = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json();

    expect($json['table']['rows'])->toHaveCount(2)
        ->and($json['resource']['abilities']['create'])->toBeFalse()
        ->and($json['table']['rows'][0]['abilities'])->toBe(['view' => true, 'update' => false, 'delete' => false, 'replicate' => false])
        ->and(ActionEvent::count())->toBe(2);
});

it('refuses every write to the log', function () {
    $event = auditEvent();

    $this->postJson('/lathe/api/resources/action-events', [])->assertForbidden();
    $this->putJson("/lathe/api/resources/action-events/{$event->id}", [])->assertForbidden();
    $this->deleteJson("/lathe/api/resources/action-events/{$event->id}")->assertForbidden();

    expect(ActionEvent::count())->toBe(1);
});

it('polls every 5 seconds and stays out of the sidebar', function () {
    $json = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json();

    expect($json['resource']['definition']['polling'])->toBe(5)
        ->and(Lathe::resources()->manifest()['action-events']['displayInNavigation'])->toBeFalse();
});

it('shows the columns of the audit log', function () {
    auditEvent();

    $json = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json();

    expect(array_column($json['table']['columns'], 'label'))->toBe([
        'ID', 'Action Name', 'Action Initiated By', 'Action Target', 'Action Status', 'Original', 'Changes', 'Exception', 'Action Happened At',
    ]);
});

it('shows the action name, the status and the changes of a row', function () {
    auditEvent(['type' => 'action-ran', 'action_name' => 'Mark Shipped', 'status' => 'running', 'original' => ['status' => 'pending'], 'changes' => ['status' => 'shipped']]);
    auditEvent(['type' => 'update', 'status' => 'failed', 'exception' => 'Boom']);

    $rows = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json('table.rows');
    [$failed, $running] = $rows;

    expect($running['cells']['action_name']['display'])->toBe('Mark Shipped')
        ->and($running['cells']['status']['type'])->toBe('loading')
        ->and($running['cells']['original']['value'])->toBe(['status' => 'pending'])
        ->and($running['cells']['changes']['value'])->toBe(['status' => 'shipped'])
        ->and($failed['cells']['action_name']['display'])->toBe('Update')
        ->and($failed['cells']['status']['type'])->toBe('failed')
        ->and($failed['cells']['exception']['display'])->toBe('Boom');
});

it('names who started a row by name, then email, then System', function () {
    $named = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $unnamed = User::where('email', 'editor@lathephp.com')->firstOrFail();
    DB::table('users')->where('id', $unnamed->id)->update(['name' => '']);

    auditEvent(['user_id' => (string) $named->id]);
    auditEvent(['user_id' => (string) $unnamed->id]);
    auditEvent(['user_id' => null]);

    $rows = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json('table.rows');

    expect(array_reverse(array_map(fn (array $row) => $row['cells']['action_initiated_by']['display'], $rows)))
        ->toBe([$named->name, 'editor@lathephp.com', 'System']);
});

it('names the target by its Resource and links it when the viewer may open it', function () {
    Gate::policy(Tag::class, AuditLogTagsNobodyOpens::class);
    $customer = Customer::query()->findOrFail(1);
    auditEvent(['type' => 'attach', 'target_type' => Customer::class, 'target_id' => '1']);
    auditEvent(['type' => 'attach', 'target_type' => Tag::class, 'target_id' => '1']);

    $rows = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json('table.rows');
    [$tag, $linked] = array_map(fn (array $row) => $row['cells']['action_target'], $rows);

    expect($linked['display'])->toBe('Customer: '.$customer->name)
        ->and($linked['value']['href'])->toBe('/lathe/resources/customers/1')
        ->and($tag['display'])->toStartWith('Tag: ')
        ->and($tag['value']['href'])->toBeNull();
});

it('shows the class name and the id for a target no Resource covers', function () {
    auditEvent(['type' => 'attach', 'target_type' => AuditLogUnlistedThing::class, 'target_id' => '1']);
    auditEvent(['type' => 'attach', 'target_type' => 'Gone\\Missing\\Thing', 'target_id' => '7']);

    $rows = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json('table.rows');

    expect($rows[1]['cells']['action_target']['display'])->toBe('AuditLogUnlistedThing #1')
        ->and($rows[1]['cells']['action_target']['value']['href'])->toBeNull()
        ->and($rows[0]['cells']['action_target']['display'])->toBe('Thing #7');
});

it('leads the breadcrumbs of an event through its record', function () {
    $event = auditEvent();

    $this->get("/lathe/resources/action-events/{$event->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('breadcrumbs', [
                ['label' => 'Dashboard', 'href' => '/lathe'],
                ['label' => 'Orders', 'href' => '/lathe/resources/orders'],
                ['label' => '#12', 'href' => '/lathe/resources/orders/12'],
                ['label' => '#'.$event->id, 'href' => null],
            ]));
});

it('keeps the plain breadcrumbs when the record or its Resource is gone', function () {
    $gone = auditEvent(['actionable_id' => '999999']);
    $unlisted = auditEvent(['actionable_type' => AuditLogUnlistedThing::class, 'actionable_id' => '1']);

    foreach ([$gone, $unlisted] as $event) {
        $this->get("/lathe/resources/action-events/{$event->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('breadcrumbs', [
                    ['label' => 'Dashboard', 'href' => '/lathe'],
                    ['label' => 'Action Events', 'href' => '/lathe/resources/action-events'],
                    ['label' => '#'.$event->id, 'href' => null],
                ]));
    }
});
