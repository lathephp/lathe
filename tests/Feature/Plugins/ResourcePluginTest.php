<?php

namespace Lathe\Tests\Feature\Plugins;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Assets\AssetRegistry;
use Lathe\Exceptions\DuplicateResourcePluginException;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Plugins\ResourcePlugin;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class ProbePanel extends ResourcePlugin
{
    protected function metaFields(): array
    {
        return ['heading'];
    }

    public function assets(): array
    {
        return ['scripts' => ['probe-panel' => dirname(__DIR__, 2).'/Fixtures/assets/beacon.js']];
    }

    public function handleRequest(Request $request, string $path): Response
    {
        return new JsonResponse(['path' => $path, 'id' => $this->resourceRecord($request)->getKey()]);
    }
}

class ClashingPanel extends ResourcePlugin
{
    public function component(): string
    {
        return 'probe-panel';
    }
}

class HiddenPanel extends ResourcePlugin
{
    //
}

/** Lets the panel list orders but never view one. */
class ProbeDenyingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderModel $order): bool
    {
        return false;
    }
}

class ProbeOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('id', '!=', 7);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Number'),
            ProbePanel::make()->heading('Probe heading'),
            HiddenPanel::make()->canSee(fn () => false),
        ];
    }
}

class ProbeTwin extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number'), ClashingPanel::make()];
    }
}

class ProbeShop extends ProbeOrder
{
    //
}

class ProbeDouble extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Number'), ProbePanel::make(), ProbePanel::make()];
    }
}

/** Counts each time a request builds it or reads its fields. */
class ProbeCounted extends Resource
{
    public static int $built = 0;

    public static function make(?Model $model = null): static
    {
        static::$built++;

        return parent::make($model);
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)->title('number')->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        static::$built++;

        return [Field::text('Number'), ProbePanel::make()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(ProbeOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('leaves a resource plugin off the create form and shows it on the detail page', function () {
    expect(array_column(LatheTest::resource('probe-orders')->creationFields()->assertOk()->json('fields'), 'key'))->toBe(['number']);

    $this->get('/lathe/resources/probe-orders/12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('record.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['number', 'actions'])
            ->where('record.resourcePlugins', [['component' => 'probe-panel', 'name' => 'Probe Panel', 'heading' => 'Probe heading', 'scripts' => ['/lathe/modules/probe-panel-6ade060b.js?resource=probe-orders']]]));
});

it('serves a resource plugin script by building only the Resource its URL names', function () {
    Lathe::registerResources(ProbeCounted::class);
    ProbeCounted::$built = 0;

    $this->get('/lathe/modules/probe-panel-6ade060b.js?resource=probe-orders')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');

    expect(ProbeCounted::$built)->toBe(0);
});

it('answers 404 for a resource plugin script without its Resource and builds no other Resource', function (string $url) {
    Lathe::registerResources(ProbeCounted::class);
    ProbeCounted::$built = 0;

    $this->get($url)->assertNotFound();

    expect(ProbeCounted::$built)->toBe(0);
})->with([
    'no key' => '/lathe/modules/probe-panel-6ade060b.js',
    'an unknown key' => '/lathe/modules/probe-panel-6ade060b.js?resource=nope',
    'a Resource without the plugin' => '/lathe/modules/probe-panel-6ade060b.js?resource=orders',
    'a wrong hash' => '/lathe/modules/probe-panel-00000000.js?resource=probe-orders',
]);

it('builds no Resource for a page or module request that does not show it', function () {
    Lathe::registerResources(ProbeCounted::class);
    Lathe::registerPlugins(Beacon::make());
    $hash = substr((string) hash_file('sha256', dirname(__DIR__, 2).'/Fixtures/assets/beacon.js'), 0, 8);
    $built = [];

    foreach (['/lathe', '/lathe/resources/orders', "/lathe/modules/beacon-{$hash}.js", '/lathe/login'] as $url) {
        if ($url === '/lathe/login') {
            auth()->guard('web')->logout();
        }

        Lathe::resourcePlugins()->flush();
        ProbeCounted::$built = 0;
        $this->get($url)->assertOk();
        $built[$url] = ProbeCounted::$built;
    }

    expect($built)->toBe(['/lathe' => 0, '/lathe/resources/orders' => 0, "/lathe/modules/beacon-{$hash}.js" => 0, '/lathe/login' => 0]);
});

it('names a plugin and its component from the class name', function () {
    expect(ProbePanel::make()->name())->toBe('Probe Panel')
        ->and(ProbePanel::make()->component())->toBe('probe-panel');
});

it('sets a declared meta key fluently and refuses an unknown one', function () {
    $panel = ProbePanel::make()->heading('Hello')->withMeta(['free' => 1]);

    expect($panel->jsonSerialize())->toBe(['component' => 'probe-panel', 'name' => 'Probe Panel', 'heading' => 'Hello', 'free' => 1]);

    ProbePanel::make()->unknown('x');
})->throws(BadMethodCallException::class);

it('registers the assets of a plugin once and indexes it by component', function () {
    Lathe::resourcePlugins()->boot();
    Lathe::resourcePlugins()->boot();

    expect(app(AssetRegistry::class)->scripts())->toHaveKey('probe-panel', dirname(__DIR__, 2).'/Fixtures/assets/beacon.js')
        ->and(Lathe::resourcePlugins()->resourceFor('probe-panel', 'probe-orders'))->toBe(ProbeOrder::class)
        ->and(Lathe::resourcePlugins()->resourceFor('hidden-panel', 'probe-orders'))->toBe(ProbeOrder::class)
        ->and(Lathe::resourcePlugins()->resourceFor('probe-panel', 'orders'))->toBeNull()
        ->and(Lathe::resourcePlugins()->resourceFor('nope', 'probe-orders'))->toBeNull();
});

it('throws at boot when two plugin classes share a component key', function () {
    Lathe::registerResources(ProbeTwin::class);

    Lathe::resourcePlugins()->boot();
})->throws(DuplicateResourcePluginException::class, 'probe-panel');

it('throws at boot when a resource places a component twice', function () {
    Lathe::registerResources(ProbeDouble::class);

    Lathe::resourcePlugins()->boot();
})->throws(DuplicateResourcePluginException::class, 'placed twice');

it('lets a subclass resource inherit a plugin and answers for either resource', function () {
    Lathe::registerResources(ProbeShop::class);

    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=probe-shops&resourceId=12')
        ->assertOk()
        ->assertExactJson(['path' => '', 'id' => 12]);
    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=probe-orders&resourceId=12')->assertOk();
});

it('forwards the api call into handleRequest with the route record', function () {
    $this->getJson('/lathe/api/resource-plugins/probe-panel/a/b?resource=probe-orders&resourceId=12')
        ->assertOk()
        ->assertExactJson(['path' => 'a/b', 'id' => 12]);
});

it('ignores an id in the query string', function () {
    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=probe-orders&resourceId=12&id=999')
        ->assertOk()
        ->assertExactJson(['path' => '', 'id' => 12]);
});

it('answers 404 for a record outside the scope, an unknown component or a mismatched resource', function () {
    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=probe-orders&resourceId=7')->assertNotFound();
    $this->getJson('/lathe/api/resource-plugins/nope?resource=probe-orders&resourceId=12')->assertNotFound();
    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=orders&resourceId=12')->assertNotFound();
    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=probe-orders')->assertNotFound();
});

it('answers 403 for a record the user may not view and for a plugin they may not see', function () {
    $this->getJson('/lathe/api/resource-plugins/hidden-panel?resource=probe-orders&resourceId=12')->assertForbidden();

    Gate::policy(OrderModel::class, ProbeDenyingPolicy::class);

    $this->getJson('/lathe/api/resource-plugins/probe-panel?resource=probe-orders&resourceId=12')->assertForbidden();
});
