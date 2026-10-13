<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Exceptions\ResourceModelCollisionException;
use Lathe\Exceptions\ResourceUriKeyCollisionException;
use Lathe\Lathe;
use Lathe\Resources\ResourceRegistry;
use Lathe\Tests\Fixtures\Models\Article as ArticleModel;
use Lathe\Tests\Fixtures\Models\Invoice as InvoiceModel;
use Lathe\Tests\Fixtures\Resources\Article;
use Lathe\Tests\Fixtures\Resources\Invoice;

/**
 * Write Resource classes into a fresh directory and return it with its namespace.
 *
 * @param  array<string, string>  $bodies  class name => configure() body
 * @return array{0: string, 1: string}
 */
function resourceDirectory(array $bodies): array
{
    $namespace = 'Generated'.bin2hex(random_bytes(4));
    $directory = sys_get_temp_dir().'/lathe-resources-'.$namespace;
    File::ensureDirectoryExists($directory);

    foreach ($bodies as $class => $body) {
        File::put("{$directory}/{$class}.php", <<<PHP
        <?php

        namespace {$namespace};

        use Lathe\Http\Requests\LatheRequest;
        use Lathe\Resources\Resource;
        use Lathe\Resources\ResourceDefinition;

        class {$class} extends Resource
        {
            protected function configure(ResourceDefinition \$definition): ResourceDefinition
            {
                return \$definition->{$body};
            }

            public function fields(LatheRequest \$request): array
            {
                return [];
            }
        }
        PHP);
    }

    return [$directory, $namespace];
}

afterEach(function () {
    app(ResourceRegistry::class)->clearCache();
});

it('derives keys and labels from the class name', function () {
    expect(Article::uriKey())->toBe('articles')
        ->and(Article::label())->toBe('Articles')
        ->and(Article::singularLabel())->toBe('Article');
});

it('maps keys and models to classes', function () {
    Lathe::registerResources(Article::class, Invoice::class);
    $registry = Lathe::resources();

    expect($registry->forKey('articles'))->toBe(Article::class)
        ->and($registry->forKey('nothing'))->toBeNull()
        ->and($registry->forModel(InvoiceModel::class))->toBe(Invoice::class)
        ->and($registry->count())->toBe(2);
});

it('records each Resource in the manifest', function () {
    Lathe::registerResources(Article::class);

    expect(Lathe::resources()->manifest()['articles'])->toBe([
        'class' => Article::class,
        'uriKey' => 'articles',
        'model' => ArticleModel::class,
        'canonical' => true,
        'label' => 'News',
        'group' => 'Content',
        'icon' => 'FileText',
        'displayInNavigation' => true,
        'globallySearchable' => false,
        'softDeletes' => true,
        'hasBadge' => false,
    ]);
});

it('defaults the title to the id attribute', function () {
    $resource = Invoice::make((new InvoiceModel)->forceFill(['id' => 42]));

    expect($resource->title())->toBe('42');
});

it('scans nothing until the manifest is first needed', function () {
    [$directory, $namespace] = resourceDirectory(['Report' => 'model(\\'.InvoiceModel::class.'::class)']);
    Lathe::discoverResources($directory, $namespace);
    Route::get('/host-page', fn () => 'host');

    $this->get('/host-page')->assertOk();
    expect(Lathe::resources()->scanned())->toBeFalse();

    expect(Lathe::resources()->forKey('reports'))->toBe($namespace.'\\Report')
        ->and(Lathe::resources()->scanned())->toBeTrue();
});

it('builds the same manifest from the cache as from a scan', function () {
    Lathe::registerResources(Article::class, Invoice::class);
    $scanned = Lathe::resources()->manifest();

    $this->artisan('lathe:cache')->assertSuccessful();
    expect(file_exists(Lathe::resources()->cachePath()))->toBeTrue();

    Lathe::resources()->flush();
    expect(Lathe::resources()->manifest())->toBe($scanned);

    $this->artisan('lathe:clear')->assertSuccessful();
    expect(file_exists(Lathe::resources()->cachePath()))->toBeFalse();
});

it('writes and clears the cache with optimize', function () {
    Lathe::registerResources(Article::class);

    $this->artisan('optimize', ['--except' => 'config,events,routes,views'])->assertSuccessful();
    expect(file_exists(Lathe::resources()->cachePath()))->toBeTrue();

    $this->artisan('optimize:clear', ['--except' => 'config,events,routes,views,cache,compiled'])->assertSuccessful();
    expect(file_exists(Lathe::resources()->cachePath()))->toBeFalse();
});

it('builds one definition for a request among 500 Resources', function () {
    $bodies = [];
    foreach (range(1, 500) as $i) {
        $bodies["Thing{$i}"] = 'model(\\'.InvoiceModel::class.'::class)->canonical(false)';
    }
    [$directory, $namespace] = resourceDirectory($bodies);
    Lathe::discoverResources($directory, $namespace);
    $this->artisan('lathe:cache')->assertSuccessful();
    Lathe::resources()->flush();

    $class = Lathe::resources()->forKey('thing250s');
    $class::make()->definition();

    expect(Lathe::resources()->definitionsBuilt())->toBe(1);
    File::deleteDirectory($directory);
});

it('names both classes and the fix on a uriKey collision', function () {
    [$directory, $namespace] = resourceDirectory([
        'Alpha' => 'model(\\'.InvoiceModel::class.'::class)->canonical(false)',
        'Beta' => 'model(\\'.ArticleModel::class.'::class)',
    ]);
    File::put("{$directory}/Beta.php", str_replace(
        'protected function configure',
        "public static function uriKey(): string\n    {\n        return 'alphas';\n    }\n\n    protected function configure",
        File::get("{$directory}/Beta.php"),
    ));
    Lathe::discoverResources($directory, $namespace);

    $messages = [];
    foreach ([1, 2] as $run) {
        Lathe::resources()->flush();
        try {
            Lathe::resources()->manifest();
        } catch (ResourceUriKeyCollisionException $e) {
            $messages[] = $e->getMessage();
        }
    }

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBe($messages[1])
        ->toContain($namespace.'\\Alpha')->toContain($namespace.'\\Beta')->toContain('uriKey()');
});

it('names both classes and the fix on a model collision', function () {
    [$directory, $namespace] = resourceDirectory([
        'Orders' => 'model(\\'.InvoiceModel::class.'::class)',
        'Sales' => 'model(\\'.InvoiceModel::class.'::class)',
    ]);
    Lathe::discoverResources($directory, $namespace);

    expect(fn () => Lathe::resources()->manifest())->toThrow(
        ResourceModelCollisionException::class,
        "Resources {$namespace}\\Orders and {$namespace}\\Sales are both canonical for ".InvoiceModel::class.'. Call canonical(false) on the extra Resource.',
    );
    $this->artisan('lathe:cache')->assertFailed()->expectsOutputToContain('canonical(false)');

});

it('lets canonical(false) clear a model collision', function () {
    [$directory, $namespace] = resourceDirectory([
        'Orders' => 'model(\\'.InvoiceModel::class.'::class)',
        'Sales' => 'model(\\'.InvoiceModel::class.'::class)->canonical(false)',
    ]);
    Lathe::discoverResources($directory, $namespace);

    expect(Lathe::resources()->forModel(InvoiceModel::class))->toBe($namespace.'\\Orders')
        ->and(Lathe::resources()->forKey('sales'))->toBe($namespace.'\\Sales');
});

it('fails naming the Resource when it has no model', function () {
    [$directory, $namespace] = resourceDirectory(['Orphan' => 'title(\'name\')']);
    Lathe::discoverResources($directory, $namespace);

    expect(fn () => Lathe::resources()->manifest())
        ->toThrow(InvalidConfigurationException::class, $namespace.'\\Orphan');
});

it('fails on a page size above the maximum', function () {
    [$directory, $namespace] = resourceDirectory(['Wide' => 'model(\\'.InvoiceModel::class.'::class)->perPage([200])']);
    Lathe::discoverResources($directory, $namespace);

    expect(fn () => Lathe::resources()->manifest())
        ->toThrow(InvalidConfigurationException::class, 'perPage');
});
