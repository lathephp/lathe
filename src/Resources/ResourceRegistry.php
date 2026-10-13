<?php

namespace Lathe\Resources;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Exceptions\InvalidSearchColumn;
use Lathe\Exceptions\ResourceModelCollisionException;
use Lathe\Exceptions\ResourceUriKeyCollisionException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Search\LikeSearch;
use Lathe\Search\SearchableColumn;
use Lathe\Search\SearchableExact;
use Lathe\Search\SearchableJson;
use Lathe\Search\SearchableMorphToRelation;
use Lathe\Search\SearchableRelation;
use Lathe\Search\SearchableText;
use Lathe\Support\ConfigStore;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Maps URI keys and models to Resource classes from a manifest, without loading
 * the Resources a request does not use. Nothing is scanned until the manifest is needed.
 *
 * @phpstan-type ManifestEntry array{class: class-string<\Lathe\Resources\Resource>, uriKey: string, model: class-string<\Illuminate\Database\Eloquent\Model>, canonical: bool, label: string, group: string|null, icon: string|null, displayInNavigation: bool, globallySearchable: bool, softDeletes: bool, hasBadge: bool, allowAll: bool, canSee: bool}
 */
class ResourceRegistry
{
    /** @var list<class-string<\Lathe\Resources\Resource>> */
    protected array $classes = [];

    /** @var list<array{0: string, 1: string}> */
    protected array $directories = [];

    /** @var array<string, ManifestEntry>|null */
    protected ?array $manifest = null;

    /** @var array<class-string<\Lathe\Resources\Resource>, ResourceDefinition> */
    protected array $definitions = [];

    protected int $definitionsBuilt = 0;

    protected bool $scanned = false;

    protected ?string $cachePath = null;

    public function __construct(
        protected Application $app,
        protected Filesystem $files,
        protected ConfigStore $config,
    ) {
        //
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  ...$classes
     */
    public function register(string ...$classes): void
    {
        $this->classes = array_values(array_unique([...$this->classes, ...$classes]));
        $this->manifest = null;
    }

    /**
     * Remember a directory of Resources; it is read when the manifest is first needed.
     */
    public function discover(string $directory, ?string $namespace = null): void
    {
        $this->directories[] = [$directory, $namespace ?? $this->namespaceFor($directory)];
        $this->manifest = null;
    }

    /**
     * @return class-string<\Lathe\Resources\Resource>|null
     */
    public function forKey(string $uriKey): ?string
    {
        return $this->manifest()[$uriKey]['class'] ?? null;
    }

    /**
     * The canonical Resource of a model.
     *
     * @return class-string<\Lathe\Resources\Resource>|null
     */
    public function forModel(string $model): ?string
    {
        foreach ($this->manifest() as $entry) {
            if ($entry['model'] === $model && $entry['canonical']) {
                return $entry['class'];
            }
        }

        return null;
    }

    /**
     * @return list<class-string<\Lathe\Resources\Resource>>
     */
    public function all(): array
    {
        return array_column($this->manifest(), 'class');
    }

    public function count(): int
    {
        return count($this->manifest());
    }

    /**
     * @return array<string, ManifestEntry>
     */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if ($this->files->exists($this->cachePath())) {
            /** @var array<string, ManifestEntry> $cached */
            $cached = require $this->cachePath();

            return $this->manifest = $cached;
        }

        return $this->manifest = $this->build();
    }

    /**
     * The frozen definition of one Resource class, built at most once.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function definition(string $class): ResourceDefinition
    {
        if (isset($this->definitions[$class])) {
            return $this->definitions[$class];
        }

        $this->definitionsBuilt++;
        $definition = (new $class)->buildDefinition();
        $this->validate($class, $definition);

        if ($definition->allowsAll()) {
            $this->app->make('log')->warning("{$class} calls ->allowAll(), so it skips every policy check.");
        }

        return $this->definitions[$class] = $definition;
    }

    public function definitionsBuilt(): int
    {
        return $this->definitionsBuilt;
    }

    public function scanned(): bool
    {
        return $this->scanned;
    }

    /**
     * Write the manifest to the cache file, once every searched column is found in its table.
     */
    public function cache(): void
    {
        $manifest = $this->build();
        $tables = [];

        foreach ($manifest as $entry) {
            $this->validateSearchColumns($entry['class'], $this->definition($entry['class']), $tables);
        }

        $this->files->ensureDirectoryExists(dirname($this->cachePath()));
        $this->files->put($this->cachePath(), '<?php return '.var_export($manifest, true).';'.PHP_EOL);
    }

    public function clearCache(): void
    {
        $this->files->delete($this->cachePath());
    }

    public function cachePath(): string
    {
        return $this->cachePath ?? $this->app->bootstrapPath('cache/lathe-resources.php');
    }

    public function useCachePath(string $path): void
    {
        $this->cachePath = $path;
    }

    /**
     * Forget everything, registered classes and directories included. For tests.
     */
    public function reset(): void
    {
        $this->classes = [];
        $this->directories = [];
        $this->flush();
    }

    /**
     * Forget the manifest and definitions; registered classes and directories stay.
     */
    public function flush(): void
    {
        $this->manifest = null;
        $this->definitions = [];
        $this->definitionsBuilt = 0;
        $this->scanned = false;
    }

    /**
     * Build the manifest by instantiating each Resource once, in a stable order.
     *
     * @return array<string, ManifestEntry>
     */
    protected function build(): array
    {
        $manifest = [];
        $canonical = [];

        foreach ($this->classes() as $class) {
            $definition = $this->definition($class);
            /** @var class-string<Model> $model */
            $model = $definition->getModel();
            $uriKey = $class::uriKey();

            if (isset($manifest[$uriKey])) {
                throw new ResourceUriKeyCollisionException(
                    "Resources {$manifest[$uriKey]['class']} and {$class} share the uriKey \"{$uriKey}\". Give one of them a distinct uriKey().",
                );
            }

            if ($definition->isCanonical()) {
                if (isset($canonical[$model])) {
                    throw new ResourceModelCollisionException(
                        "Resources {$canonical[$model]} and {$class} are both canonical for {$model}. Call canonical(false) on the extra Resource.",
                    );
                }

                $canonical[$model] = $class;
            }

            $manifest[$uriKey] = [
                'class' => $class,
                'uriKey' => $uriKey,
                'model' => $model,
                'canonical' => $definition->isCanonical(),
                'label' => $definition->getNavigationLabel() ?? $class::label(),
                'group' => $definition->getGroup(),
                'icon' => $definition->getIcon(),
                'displayInNavigation' => $definition->shouldDisplayInNavigation(),
                'globallySearchable' => $definition->isGloballySearchable(),
                'softDeletes' => $definition->usesSoftDeletes(),
                'hasBadge' => false,
                'allowAll' => $definition->allowsAll(),
                'canSee' => $definition->hasSeeCallback(),
            ];
        }

        return $manifest;
    }

    /**
     * Registered and discovered classes, sorted so every run visits them in one order.
     *
     * @return list<class-string<\Lathe\Resources\Resource>>
     */
    protected function classes(): array
    {
        $classes = $this->classes;

        foreach ($this->directories as [$directory, $namespace]) {
            $classes = [...$classes, ...$this->scan($directory, $namespace)];
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string<\Lathe\Resources\Resource>>
     */
    protected function scan(string $directory, string $namespace): array
    {
        $this->scanned = true;

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $classes = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php')->sortByName() as $file) {
            $class = $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (! class_exists($class)) {
                require_once $file->getRealPath();
            }

            if (is_subclass_of($class, Resource::class) && ! (new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function validate(string $class, ResourceDefinition $definition): void
    {
        if ($definition->getModel() === null) {
            throw new InvalidConfigurationException("{$class} has no model. Call ->model(YourModel::class) in its configure() method.");
        }

        if ($definition->getUseScout() === true && ! $definition->modelIsScoutSearchable()) {
            throw new InvalidConfigurationException("{$class}: useScout(true) needs the Laravel\\Scout\\Searchable trait on {$definition->getModel()}. Add the trait or remove useScout(true).");
        }

        $max = $this->config->integer('limits.max_per_page');

        foreach ($definition->getPerPage() ?? [] as $option) {
            if ($option < 1 || $option > $max) {
                throw new InvalidConfigurationException("{$class}: perPage: every value must be an integer from 1 to {$max}; got {$option}. Set limits.max_per_page higher or remove {$option}.");
            }
        }

        if (($cap = $definition->getCountCap()) < 1) {
            throw new InvalidConfigurationException("{$class}: countStrategy: the cap must be at least 1; got {$cap}. Pass a cap of 1 or more, or leave it out to use limits.count_cap.");
        }

        if (($polling = $definition->getPolling()) !== null && $polling < 1) {
            throw new InvalidConfigurationException("{$class}: polling: the interval must be at least 1 second; got {$polling}. Pass 1 or more, or remove polling().");
        }

        /** @var Model $model */
        $model = new ($definition->getModel());
        /** @var list<mixed> $searchable */
        $searchable = $definition->getSearchable();

        foreach ($searchable as $index => $column) {
            if (is_string($column)) {
                continue;
            }

            if (! $column instanceof SearchableColumn) {
                $name = is_object($column) ? (get_object_vars($column)['column'] ?? null) : null;
                $label = (is_object($column) ? $column::class : get_debug_type($column)).(is_string($name) ? " (column \"{$name}\")" : " (entry {$index})");

                throw new InvalidSearchColumn("{$class}: searchable: {$label} does not implement ".SearchableColumn::class.'. Implement that interface or use a strategy from Lathe\\Search.');
            }

            if (($column instanceof SearchableRelation || $column instanceof SearchableMorphToRelation) && ! $model->isRelation($column->relation)) {
                throw new InvalidSearchColumn("{$class}: searchable: column \"{$column->column}\" uses the relation \"{$column->relation}\", which ".$model::class." does not define. Add a {$column->relation}() relation to the model or remove the column from searchable().");
            }
        }

        // A to-many relation repeats each row once per related row, so only a belongsTo can group.
        foreach ((new $class)->groups(LatheRequest::createFrom(Request::create('/'))) as $group) {
            $relation = $group->relation();

            if ($relation !== null && ! ($model->isRelation($relation) && $model->{$relation}() instanceof BelongsTo)) {
                throw new InvalidConfigurationException("{$class}: groups: \"{$group->column()}\" reads \"{$relation}\", which is not a belongsTo relation of ".$model::class.'. Group by a column of the model or of a belongsTo relation.');
            }
        }
    }

    /**
     * Check that every searched column is in its table. This reads the database, one column
     * listing per table, so only lathe:cache runs it: a request that builds the manifest
     * itself makes no query for it. A missing table or a database that cannot be reached,
     * as in a deploy build step, skips the check; lathe:doctor reports those.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  array<string, list<string>>  $tables  the column listings read so far, by connection and table
     */
    protected function validateSearchColumns(string $class, ResourceDefinition $definition, array &$tables): void
    {
        /** @var Model $model */
        $model = new ($definition->getModel());

        foreach ($definition->getSearchable() as $column) {
            foreach ($this->searchTargets($model, $column) as [$target, $name]) {
                $table = $target->getTable();
                $columns = $tables[$target->getConnectionName().'.'.$table] ??= $this->columnListing($target);

                if ($columns !== [] && ! in_array($name, $columns, true)) {
                    throw new InvalidSearchColumn("{$class}: searchable: column \"{$name}\" is not in the {$table} table. Add the column with a migration or remove it from searchable().");
                }
            }
        }
    }

    /**
     * The models and columns one declared search column reads. A morph relation with
     * types [] reads types known only from its rows, so it names none.
     *
     * @return list<array{0: Model, 1: string}>
     */
    protected function searchTargets(Model $model, string|SearchableColumn $column): array
    {
        return match (true) {
            is_string($column) => [[$model, $column]],
            $column instanceof LikeSearch, $column instanceof SearchableExact, $column instanceof SearchableText => [[$model, $column->column]],
            $column instanceof SearchableJson => [[$model, Str::before($column->path, '->')]],
            $column instanceof SearchableRelation => ($relation = $model->{$column->relation}()) instanceof Relation ? [[$relation->getRelated(), $column->column]] : [],
            $column instanceof SearchableMorphToRelation => array_map(fn (string $type) => [new $type, $column->column], $column->types),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    protected function columnListing(Model $model): array
    {
        try {
            return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        } catch (Throwable) {
            return [];
        }
    }

    protected function namespaceFor(string $directory): string
    {
        $relative = trim(str_replace($this->app->path(), '', $directory), DIRECTORY_SEPARATOR);

        return rtrim($this->app->getNamespace(), '\\').($relative === '' ? '' : '\\'.str_replace('/', '\\', $relative));
    }
}
