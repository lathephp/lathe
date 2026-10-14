<?php

use Acme\Maps\Maps;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Lathe\Actions\Action;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;
use Symfony\Component\Process\Process;
use Workbench\App\Lathe\Resources\Category;
use Workbench\App\Lathe\Resources\Comment;
use Workbench\App\Lathe\Resources\Customer;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Lathe\Resources\OrderItem;
use Workbench\App\Lathe\Resources\Post;
use Workbench\App\Lathe\Resources\Product;
use Workbench\App\Lathe\Resources\Tag;
use Workbench\App\Lathe\Resources\User;

require_once __DIR__.'/records.php';

uses(GeneratesIntoTempApp::class);

// The sidebar section headed Maps.
const MAPS_SECTION = '[data-lathe-sidebar] div:has(> h2:text-is("Maps"))';

/**
 * The workbench Order Resource with the generated Archive action added to its actions().
 */
class ArchivingOrder extends Order
{
    public static function uriKey(): string
    {
        return 'orders';
    }

    public static function label(): string
    {
        return 'Orders';
    }

    public static function singularLabel(): string
    {
        return 'Order';
    }

    public function actions(LatheRequest $request): array
    {
        /** @var class-string<Action> $archive */
        $archive = 'App\\Lathe\\Actions\\Archive';

        return [...parent::actions($request), new $archive];
    }
}

it('shows the acme/maps plugin page in the sidebar and the palette after its build', function () {
    holdBuildLock();
    $repo = dirname(__DIR__, 2);
    // Every step shares one 30 minute budget.
    $deadline = time() + 1800;

    // The plugin goes into its own host folder; the panel keeps the workbench paths.
    $host = sys_get_temp_dir().'/lathe-maps-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($host);
    File::put("{$host}/composer.json", '{}');
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($host));
    $this->app->instance('lathe.host_path', $host);

    $this->artisan('lathe:plugin', ['name' => 'acme/maps', '--skip-composer' => true])->assertExitCode(0);
    $plugin = "{$host}/lathe-components/Maps";

    // The Heartbeat workspace package holds the dev dependencies the skeleton names.
    $modules = "{$repo}/workbench/lathe-components/Heartbeat/node_modules";
    File::ensureDirectoryExists("{$plugin}/node_modules");

    foreach (array_diff((array) scandir($modules), ['.', '..', '.vite-temp']) as $entry) {
        symlink("{$modules}/{$entry}", "{$plugin}/node_modules/{$entry}");
    }

    if (! is_file("{$repo}/packages/vite-plugin/dist/index.js")) {
        (new Process(['bash', 'scripts/pnpm.sh', '--config.verify-deps-before-run=false', '--filter', '@lathephp/vite-plugin', 'build'], $repo, timeout: $deadline - time()))->mustRun();
    }

    $build = new Process(['bash', "{$repo}/scripts/pnpm.sh", '--config.verify-deps-before-run=false', 'exec', 'vite', 'build'], $plugin, timeout: $deadline - time());
    $build->run();
    expect($build->getExitCode())->toBe(0, $build->getOutput().$build->getErrorOutput());

    // The in-process test server trims a file response's body but keeps its Content-Length, so a
    // module too small to be gzipped and ending in a newline never finishes loading in the browser.
    File::put("{$plugin}/dist/maps.js", rtrim(File::get("{$plugin}/dist/maps.js")));

    ClassLoader::getRegisteredLoaders()[array_key_first(ClassLoader::getRegisteredLoaders())]->addPsr4('Acme\\Maps\\', "{$plugin}/src/");
    Lathe::registerPlugins(new Maps);
    reloadLatheRoutes();

    $page = signedInAt('/lathe');

    $page->assertCount(MAPS_SECTION.' li', 1)
        ->assertSeeIn(MAPS_SECTION.' li', 'Maps')
        ->click(MAPS_SECTION.' li a')
        ->assertPathIs('/lathe/plugins/maps')
        ->assertSee('Edit resources/js/plugin.tsx to build this page.');
    assertCleanPage($page);

    $page->navigate('/lathe')
        ->keys('#app', 'Control+k')
        ->type('[role="combobox"]', 'maps')
        ->assertCount('[role="group"][aria-label="Maps"] [role="option"]', 1)
        ->assertSeeIn('[role="group"][aria-label="Maps"] [role="option"]', 'Maps')
        ->keys('[role="combobox"]', 'Enter')
        ->assertPathIs('/lathe/plugins/maps')
        ->assertSee('Edit resources/js/plugin.tsx to build this page.');
    assertCleanPage($page);
})->group('scaffold');

it('lists the generated Archive action in the Orders bulk menu', function () {
    // The generator writes into a temp app; the panel goes back to the workbench paths after.
    $basePath = $this->app->basePath();
    $appPath = $this->app->path();
    $base = $this->generatorApp();

    $this->artisan('lathe:action', ['name' => 'Archive'])->assertExitCode(0);
    require_once "{$base}/app/Lathe/Actions/Archive.php";

    $this->app->setBasePath($basePath);
    $this->app->useAppPath($appPath);

    Lathe::resources()->reset();
    Lathe::registerResources(ArchivingOrder::class, Category::class, Comment::class, Customer::class, OrderItem::class, Post::class, Product::class, Tag::class, User::class);

    $page = signedInAt('/lathe/resources/orders');

    $page->click('tbody tr:first-child input[type="checkbox"]')
        ->click('button[aria-haspopup="menu"]:text-is("Actions")')
        ->assertPresent('[role="menu"] [role="menuitem"]:has-text("Archive")');
    assertCleanPage($page);
});
