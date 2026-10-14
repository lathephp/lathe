<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Auth\Contracts\ImpersonatesUsers;
use Lathe\Auth\Impersonation;
use Lathe\Enums\ActionEventType;
use Lathe\Events\ImpersonationStarted;
use Lathe\Events\ImpersonationStopped;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\User as UserResource;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** The users of one tenant: a detail lookup reaches only the lathephp.com addresses. */
class ImpersonationTenantUsers extends UserResource
{
    public static function uriKey(): string
    {
        return 'tenant-users';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('email'), 'like', '%@lathephp.com');
    }
}

/** A user the app shields from impersonation. */
class ImpersonationShieldedUser extends User
{
    protected $table = 'users';

    public function canBeImpersonated(): bool
    {
        return false;
    }
}

class ImpersonationShieldedUsers extends UserResource
{
    public static function uriKey(): string
    {
        return 'shielded-users';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->model(ImpersonationShieldedUser::class)->canonical(false);
    }
}

const IMPERSONATION_KEYS = ['lathe_impersonated_by', 'lathe_impersonated_remember', 'lathe_impersonated_from', 'lathe_impersonated_guard'];

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    // Untyped, so a user model other than the workbench one still reaches the endpoint.
    Gate::define('viewLathe', fn ($user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    Lathe::registerResources(ImpersonationTenantUsers::class, ImpersonationShieldedUsers::class);

    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

function startImpersonating(int|string $id, string $resource = 'users', array $headers = []): TestResponse
{
    return test()->postJson('/lathe/api/impersonate', ['resource' => $resource, 'resourceId' => $id], $headers);
}

// Starting

it('starts impersonating the editor and redirects to the configured page', function () {
    $this->actingAs($this->admin);

    startImpersonating($this->editor->id, headers: ['Referer' => '/lathe/resources/users'])
        ->assertOk()
        ->assertExactJson(['redirect' => '/lathe']);

    expect(session('lathe_impersonated_by'))->toBe($this->admin->id)
        ->and(session('lathe_impersonated_remember'))->toBeFalse()
        ->and(session('lathe_impersonated_from'))->toBe('/lathe/resources/users')
        ->and(session()->has('lathe_impersonated_guard'))->toBeFalse();

    $this->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.name', 'Editor User'));
    $this->getJson('/lathe/api/impersonate')->assertOk()->assertExactJson(['impersonating' => true]);
});

it('answers impersonating false before any start', function () {
    $this->actingAs($this->admin);

    $this->getJson('/lathe/api/impersonate')->assertOk()->assertExactJson(['impersonating' => false]);
});

// Refusals

it('refuses self-impersonation whether the id arrives as an int or a string', function (bool $asString) {
    $this->actingAs($this->admin);

    startImpersonating($asString ? (string) $this->admin->id : $this->admin->id)
        ->assertForbidden()
        ->assertExactJson(['message' => 'You cannot impersonate yourself.']);

    expect(session()->has('lathe_impersonated_by'))->toBeFalse();
})->with(['int id' => false, 'string id' => true]);

it('refuses the editor, whom the workbench User model does not let impersonate', function () {
    $this->actingAs($this->editor);

    startImpersonating($this->admin->id)->assertForbidden();

    expect(session()->has('lathe_impersonated_by'))->toBeFalse();
});

it('refuses a target whose model says it cannot be impersonated', function () {
    $this->actingAs($this->admin);

    startImpersonating($this->editor->id, 'shielded-users')->assertForbidden();

    expect(session()->has('lathe_impersonated_by'))->toBeFalse();
});

it('answers 404 to an unknown resource or id', function () {
    $this->actingAs($this->admin);

    startImpersonating($this->editor->id, 'nopes')->assertNotFound();
    startImpersonating(999999)->assertNotFound();
    startImpersonating($this->editor->id)->assertOk();
});

it('finds the target through the scoped query, so another tenant\'s user is 404', function () {
    $outsider = User::factory()->create(['email' => 'outsider@acme.test']);
    $this->actingAs($this->admin);

    startImpersonating($outsider->id, 'tenant-users')->assertNotFound();
    startImpersonating($this->editor->id, 'tenant-users')->assertOk();
});

it('answers 401 when signed out', function () {
    startImpersonating(2)->assertUnauthorized();
    $this->getJson('/lathe/api/impersonate')->assertUnauthorized();
    $this->deleteJson('/lathe/api/impersonate')->assertUnauthorized();

    $this->actingAs($this->admin)->getJson('/lathe/api/impersonate')->assertOk();
});

// Stopping

it('stops on a second POST and on DELETE, back to the admin', function (string $method) {
    $this->actingAs($this->admin);
    startImpersonating($this->editor->id)->assertOk();

    $response = $method === 'post' ? startImpersonating($this->editor->id) : $this->deleteJson('/lathe/api/impersonate');

    $response->assertOk()->assertExactJson(['redirect' => '/']);
    $this->getJson('/lathe/api/impersonate')->assertExactJson(['impersonating' => false]);
    $this->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.name', 'Admin User'));

    foreach (IMPERSONATION_KEYS as $key) {
        expect(session()->has($key))->toBeFalse();
    }
})->with(['second POST' => 'post', 'DELETE' => 'delete']);

it('signs the admin back in remembered when they were remembered', function (bool $remembered) {
    $this->actingAs($this->admin);
    startImpersonating($this->editor->id)->assertOk();
    session()->put('lathe_impersonated_remember', $remembered);

    $response = $this->deleteJson('/lathe/api/impersonate')->assertOk();

    $recaller = Auth::guard('web')->getRecallerName();
    $remembered ? $response->assertCookie($recaller) : $response->assertCookieMissing($recaller);
})->with(['remembered' => true, 'not remembered' => false]);

it('clears the session and stops nothing when the original user was deleted', function () {
    $this->actingAs($this->admin);
    startImpersonating($this->editor->id)->assertOk();
    DB::table('users')->where('id', $this->admin->id)->delete();

    $this->deleteJson('/lathe/api/impersonate')->assertOk()->assertExactJson(['redirect' => '/']);

    foreach (IMPERSONATION_KEYS as $key) {
        expect(session()->has($key))->toBeFalse();
    }

    $this->getJson('/lathe/api/impersonate')->assertExactJson(['impersonating' => false]);
});

it('answers false from stopImpersonating when the original user is gone', function () {
    $request = Request::create('/lathe/api/impersonate', 'DELETE');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put(['lathe_impersonated_by' => 999999, 'lathe_impersonated_remember' => false, 'lathe_impersonated_from' => '/lathe']);

    expect(Lathe::impersonator()->stopImpersonating($request, Auth::guard('web'), User::class))->toBeFalse()
        ->and($request->session()->has('lathe_impersonated_by'))->toBeFalse()
        ->and($request->session()->has('lathe_impersonated_from'))->toBeFalse();
});

it('records a second guard and signs it out on stop', function () {
    config([
        'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users'],
        'lathe.guard' => 'admin',
    ]);
    Auth::guard('admin')->login($this->admin);
    $request = Request::create('/lathe/api/impersonate', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn (?string $guard = null) => Auth::guard($guard)->user());

    Lathe::impersonator()->impersonate($request, Auth::guard('web'), $this->editor);

    expect($request->session()->get('lathe_impersonated_guard'))->toBe('web')
        ->and(Auth::guard('web')->user()?->is($this->editor))->toBeTrue();

    expect(Lathe::impersonator()->stopImpersonating($request, Auth::guard('admin'), User::class))->toBeTrue()
        ->and(Auth::guard('web')->user())->toBeNull()
        ->and(Auth::guard('admin')->user()?->is($this->admin))->toBeTrue()
        ->and($request->session()->has('lathe_impersonated_guard'))->toBeFalse();
});

// Redirects

it('returns to the saved referer only when it is same-origin', function (string $referer, string $redirect) {
    $this->actingAs($this->admin);

    $this->postJson('http://127.0.0.1:8000/lathe/api/impersonate', ['resource' => 'users', 'resourceId' => $this->editor->id], ['Referer' => $referer])
        ->assertOk();

    $this->deleteJson('http://127.0.0.1:8000/lathe/api/impersonate')->assertOk()->assertExactJson(['redirect' => $redirect]);
})->with([
    'relative path' => ['/lathe/resources/users', '/lathe/resources/users'],
    'same host' => ['http://127.0.0.1:8000/lathe/resources/users', 'http://127.0.0.1:8000/lathe/resources/users'],
    'protocol-relative' => ['//evil.test', '/'],
    'backslash' => ['/\evil.test', '/'],
    'other host' => ['https://evil.test/x', '/'],
]);

it('honours the configured start and stop pages', function () {
    config(['lathe.impersonation.started' => '/start-here', 'lathe.impersonation.stopped' => '/stop-here']);
    $this->actingAs($this->admin);

    startImpersonating($this->editor->id)->assertExactJson(['redirect' => '/start-here']);
    $this->deleteJson('/lathe/api/impersonate')->assertExactJson(['redirect' => '/stop-here']);
});

// Events

it('dispatches the start and stop events with the redirect', function () {
    Event::fake([ImpersonationStarted::class, ImpersonationStopped::class]);
    $this->actingAs($this->admin);

    startImpersonating($this->editor->id, headers: ['Referer' => '/lathe/resources/users'])->assertOk();
    $this->deleteJson('/lathe/api/impersonate')->assertOk();

    Event::assertDispatched(ImpersonationStarted::class, fn (ImpersonationStarted $event) => $event->impersonator->is($this->admin)
        && $event->impersonated->is($this->editor)
        && $event->redirectTo === '/lathe');
    Event::assertDispatched(ImpersonationStopped::class, fn (ImpersonationStopped $event) => $event->impersonator->is($this->admin)
        && $event->impersonated->is($this->editor)
        && $event->redirectTo === '/lathe/resources/users');
});

it('writes one audit row for the start and one for the stop, initiated by the admin', function () {
    $this->actingAs($this->admin);

    startImpersonating($this->editor->id)->assertOk();
    $this->deleteJson('/lathe/api/impersonate')->assertOk();

    $rows = ActionEvent::orderBy('id')->get();

    expect($rows->pluck('type')->all())->toBe([ActionEventType::ImpersonationStarted, ActionEventType::ImpersonationStopped])
        ->and($rows->map(fn (ActionEvent $row) => (string) $row->user_id)->all())->toBe([(string) $this->admin->id, (string) $this->admin->id])
        ->and($rows->map(fn (ActionEvent $row) => (string) $row->actionable_id)->all())->toBe([(string) $this->editor->id, (string) $this->editor->id]);

    $cells = $this->getJson('/lathe/api/resources/action-events')->assertOk()->json('table.rows.*.cells.action_initiated_by.display');

    expect($cells)->toBe(['Admin User', 'Admin User']);
});

// The binding

it('resolves the impersonator from the container, so a rebinding reaches the controller', function () {
    expect(Lathe::impersonator())->toBeInstanceOf(Impersonation::class);

    $custom = new class extends Impersonation
    {
        public function redirectAfterStartingImpersonation(Request $request): string
        {
            return '/custom-start';
        }
    };
    app()->bind(ImpersonatesUsers::class, fn () => $custom);
    $this->actingAs($this->admin);

    expect(Lathe::impersonator())->toBe($custom);
    startImpersonating($this->editor->id)->assertOk()->assertExactJson(['redirect' => '/custom-start']);
});
