<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lathe\ActionEvents\ActionEvent;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Enums\ActionEventStatus;
use Lathe\Enums\ActionEventType;
use Lathe\Events\ImpersonationStarted;
use Lathe\Events\ImpersonationStopped;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SecretCustomerResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name'), Field::token('Vault key', 'vault')];
    }
}

class QuietCustomerResource extends SecretCustomerResource
{
    public static function recordsActionEvents(): bool
    {
        return false;
    }
}

class KeyedUser extends Authenticatable
{
    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

function panelRequest(array $server = []): LatheRequest
{
    return LatheRequest::create('/lathe/api/resources/customers', 'POST', server: $server);
}

function recorder(): ActionEventRecorder
{
    return app(ActionEventRecorder::class);
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
    $this->customer = Customer::query()->findOrFail(1);
});

// modelState

it('drops hidden attributes and shrinks files and long strings', function () {
    $user = new User(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'hunter2']);
    $user->setAttribute('remember_token', 'abc');
    $user->setAttribute('avatar', UploadedFile::fake()->create('me.png', 12, 'image/png'));
    $user->setAttribute('bio', str_repeat('x', 1001));
    $user->setAttribute('api_token', 'secret-value');
    $user->setAttribute('stripe_token', 'secret-value');

    $state = recorder()->modelState($user);

    expect($state)->not->toHaveKey('password')
        ->and($state)->not->toHaveKey('remember_token')
        ->and($state['name'])->toBe('Ada')
        ->and($state['avatar'])->toBe(['name' => 'me.png', 'size' => 12 * 1024, 'mime' => 'image/png'])
        ->and($state['bio'])->toBe(str_repeat('x', 1000).'...(truncated)')
        ->and($state['api_token'])->toBe('[redacted]')
        ->and($state['stripe_token'])->toBe('[redacted]');
});

it('redacts the attributes a Resource declares as token fields', function () {
    recorder()->forResourceUpdate(panelRequest(), $this->customer, SecretCustomerResource::class, ['vault' => 'old', 'name' => 'A'], ['vault' => 'new', 'name' => 'B']);

    $row = ActionEvent::sole();

    expect($row->original)->toMatchStoredJson(['vault' => '[redacted]', 'name' => 'A'])
        ->and($row->changes)->toMatchStoredJson(['vault' => '[redacted]', 'name' => 'B']);
});

it('records the request, with the user agent cut to 255 characters', function () {
    recorder()->forResourceCreate(panelRequest(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => str_repeat('u', 300)]), $this->customer, SecretCustomerResource::class);

    $row = ActionEvent::sole();

    expect($row->ip_address)->toBe('203.0.113.7')
        ->and($row->user_agent)->toBe(str_repeat('u', 255))
        ->and((string) $row->user_id)->toBe((string) $this->admin->id);
});

// forActionRan

it('inserts an action run in chunks of 50 under one batch id', function () {
    $models = new Collection;

    foreach (range(1, 120) as $i) {
        $models->push(tap(new Order, fn (Order $order) => $order->forceFill(['id' => $i])->exists = true));
    }

    DB::enableQueryLog();
    $batch = recorder()->forActionRan(panelRequest(), 'mark-shipped', $models, SecretCustomerResource::class, ActionEventStatus::Running, ['note' => 'hi']);
    $inserts = collect(DB::getQueryLog())->filter(fn (array $q) => str_starts_with($q['query'], 'insert into'))->count();

    expect($inserts)->toBe(3)
        ->and(ActionEvent::count())->toBe(120)
        ->and(ActionEvent::distinct()->pluck('batch_id')->all())->toBe([$batch])
        ->and(ActionEvent::first()->type)->toBe(ActionEventType::ActionRan)
        ->and(ActionEvent::first()->action_name)->toBe('mark-shipped')
        ->and(ActionEvent::first()->status)->toBe(ActionEventStatus::Running)
        ->and(ActionEvent::first()->fields)->toBe(['note' => 'hi']);
});

it('writes one row with no record for a standalone run', function () {
    $batch = recorder()->forActionRan(panelRequest(), 'rebuild-index', new Collection, SecretCustomerResource::class, ActionEventStatus::Finished);

    $row = ActionEvent::sole();

    expect($row->batch_id)->toBe($batch)
        ->and($row->actionable_type)->toBeNull()
        ->and($row->actionable_id)->toBeNull()
        ->and($row->status)->toBe(ActionEventStatus::Finished);
});

// Status

it('never touches a row that is already finished or failed', function () {
    $batch = recorder()->forActionRan(panelRequest(), 'ship', new Collection([$this->customer, Customer::query()->findOrFail(2), Customer::query()->findOrFail(3)]), SecretCustomerResource::class, ActionEventStatus::Waiting);

    ActionEvent::markBatchAsRunning($batch);
    expect(ActionEvent::forBatch($batch)->pluck('status')->unique()->all())->toBe([ActionEventStatus::Running]);

    ActionEvent::markAsFinished($batch, $this->customer);
    ActionEvent::markAsFailed($batch, Customer::query()->findOrFail(2), new RuntimeException('boom'));

    ActionEvent::markBatchAsFinished($batch);
    ActionEvent::markBatchAsFailed($batch, new RuntimeException('late'));
    ActionEvent::markAsFailed($batch, $this->customer);

    $rows = ActionEvent::forBatch($batch)->orderBy('actionable_id')->get();

    expect($rows->pluck('status')->all())->toBe([ActionEventStatus::Finished, ActionEventStatus::Failed, ActionEventStatus::Finished])
        ->and($rows[1]->exception)->toBe('boom')
        ->and(ActionEventStatus::terminal())->toBe([ActionEventStatus::Finished, ActionEventStatus::Failed]);
});

it('fails a whole batch that is still running, with the message', function () {
    $batch = recorder()->forActionRan(panelRequest(), 'ship', new Collection([$this->customer]), SecretCustomerResource::class, ActionEventStatus::Waiting);

    ActionEvent::markBatchAsFailed($batch, new RuntimeException('queue down'));

    expect(ActionEvent::sole()->status)->toBe(ActionEventStatus::Failed)
        ->and(ActionEvent::sole()->exception)->toBe('queue down');
});

// Switches

it('writes nothing when the global switch is off', function () {
    config(['lathe.action_events.enabled' => false]);

    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

    expect(ActionEvent::count())->toBe(0)->and(recorder()->isEnabled())->toBeFalse();
});

it('writes nothing for a Resource that turns recording off', function () {
    recorder()->forResourceCreate(panelRequest(), $this->customer, QuietCustomerResource::class);

    expect(ActionEvent::count())->toBe(0);
});

it('turns recording off only inside withoutActionEvents with a callback', function () {
    $result = Lathe::withoutActionEvents(function () {
        recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

        return 'ran';
    });

    expect($result)->toBe('ran')->and(ActionEvent::count())->toBe(0);

    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

    expect(ActionEvent::count())->toBe(1);
});

it('turns recording off for the rest of the request without a callback, and on for the next', function () {
    Lathe::withoutActionEvents();
    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

    expect(ActionEvent::count())->toBe(0);

    app()->forgetScopedInstances();
    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

    expect(ActionEvent::count())->toBe(1);
});

it('swaps the model the recorder writes', function () {
    Lathe::usingActionEvent(CustomActionEvent::class);

    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

    expect(Lathe::actionEventModel())->toBe(CustomActionEvent::class)
        ->and(CustomActionEvent::count())->toBe(1);

    Lathe::usingActionEvent(ActionEvent::class);
});

class CustomActionEvent extends ActionEvent
{
    //
}

// Impersonation

it('records impersonation start and stop against the impersonated user', function () {
    $editor = User::where('email', 'editor@lathephp.com')->firstOrFail();

    event(new ImpersonationStarted($this->admin, $editor, '/lathe'));
    event(new ImpersonationStopped($this->admin, $editor, '/'));

    $rows = ActionEvent::orderBy('id')->get();

    expect($rows->pluck('type')->all())->toBe([ActionEventType::ImpersonationStarted, ActionEventType::ImpersonationStopped])
        ->and((string) $rows[0]->user_id)->toBe((string) $this->admin->id)
        ->and($rows[0]->actionable_type)->toBe(User::class)
        ->and((string) $rows[0]->actionable_id)->toBe((string) $editor->id);
});

// Models

it('labels the 11 types', function () {
    expect(ActionEventType::cases())->toHaveCount(11)
        ->and(ActionEventType::ForceDelete->label())->toBe('Force Delete')
        ->and(ActionEventType::UpdateAttached->label())->toBe('Update Attached')
        ->and(ActionEventType::ImpersonationStopped->value)->toBe('impersonation-stopped');
});

it('lists the events of a record through the Actionable trait', function () {
    $order = Order::query()->findOrFail(1);
    recorder()->forResourceCreate(panelRequest(), $order, SecretCustomerResource::class);
    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);

    expect($order->actions)->toHaveCount(1)
        ->and(ActionEvent::forActionable($order)->count())->toBe(1)
        ->and(ActionEvent::ofType(ActionEventType::Create)->count())->toBe(2)
        ->and(ActionEvent::byUser($this->admin)->count())->toBe(2);
});

it('prunes rows older than the configured days', function () {
    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);
    recorder()->forResourceCreate(panelRequest(), $this->customer, SecretCustomerResource::class);
    ActionEvent::query()->limit(1)->update(['created_at' => now()->subDays(31)]);

    expect((new ActionEvent)->prunable()->count())->toBe(1);
});

it('works with an integer, UUID, ULID or string user key', function (string $keyType, Closure $makeKey) {
    Schema::dropAllTables();
    Schema::create('users', function ($table) use ($keyType) {
        match ($keyType) {
            'int' => $table->id(),
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            'string' => $table->string('id')->primary(),
        };
        $table->string('name');
    });
    DB::table('users')->insert(array_filter(['id' => $keyType === 'int' ? null : $makeKey(), 'name' => 'Ada']));
    $this->artisan('migrate', ['--path' => dirname(__DIR__, 3).'/database/migrations', '--realpath' => true])->assertExitCode(0);

    config(['auth.providers.users.model' => KeyedUser::class]);
    $user = KeyedUser::query()->firstOrFail();
    $this->actingAs($user);

    recorder()->forResourceCreate(panelRequest(), $user, SecretCustomerResource::class);

    $row = ActionEvent::sole();

    expect($row->user_id)->toBe((string) $user->getKey())
        ->and($row->user?->getKey())->toEqual($user->getKey())
        ->and($row->actionable?->getKey())->toEqual($user->getKey());
})->with([
    'int key' => ['int', fn () => 7],
    'uuid key' => ['uuid', fn () => (string) Str::uuid()],
    'ulid key' => ['ulid', fn () => (string) Str::ulid()],
    'string key' => ['string', fn () => 'ada-lovelace'],
]);
