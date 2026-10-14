<?php

namespace Lathe\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Plugins\Plugin;
use Lathe\Resources\Resource;
use PHPUnit\Framework\Assert;

/**
 * Helpers a test of a Lathe panel uses: register Resources, reset the registry, count queries, check scoping.
 */
trait InteractsWithLathe
{
    /**
     * Build a panel request without sending it.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function latheRequest(string $uri = '/lathe/api', string $method = 'GET', array $parameters = []): LatheRequest
    {
        return LatheRequest::createFrom(Request::create($uri, $method, $parameters));
    }

    /**
     * Register Resources for this test without a provider.
     *
     * @param  class-string<\Lathe\Resources\Resource>  ...$classes
     */
    protected function registerResources(string ...$classes): void
    {
        Lathe::registerResources(...$classes);
    }

    /**
     * Register dashboards for this test without a provider.
     *
     * @param  Dashboard|class-string<Dashboard>  ...$dashboards
     */
    protected function registerDashboards(Dashboard|string ...$dashboards): void
    {
        Lathe::registerDashboards(...$dashboards);
    }

    /**
     * Register plugins for this test without a provider.
     */
    protected function registerPlugins(Plugin ...$plugins): void
    {
        Lathe::registerPlugins(...$plugins);
    }

    /**
     * Forget every registered Resource and the cached manifest.
     */
    protected function flushLathe(): void
    {
        Lathe::resources()->reset();
        Lathe::resources()->clearCache();
    }

    /**
     * Fail unless the closure runs exactly $expected queries; the failure lists the SQL.
     *
     * @param  Closure(): mixed  $request
     */
    protected function assertQueryCount(int $expected, Closure $request): void
    {
        $queries = $this->queriesDuring($request);
        $listing = collect($queries)->map(fn (string $sql, int $i) => ($i + 1).'. '.$sql)->implode("\n");

        Assert::assertCount($expected, $queries, "Expected {$expected} queries, ran ".count($queries).".\n".$listing);
    }

    /**
     * Fail when a query on the model's table runs without the column its global scopes filter on.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(): mixed  $request
     */
    protected function assertScoped(string $model, Closure $request): void
    {
        $instance = new $model;
        $columns = $this->scopeColumns($instance);

        Assert::assertNotEmpty($columns, "{$model} has no global scope to check.");

        $table = preg_quote($instance->getTable(), '/');
        $unscoped = [];

        foreach ($this->queriesDuring($request) as $sql) {
            if (! preg_match('/\b(from|join)\s+["`\[]?'.$table.'["`\]]?(\s|$)/i', $sql)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! preg_match('/\b'.preg_quote($column, '/').'["`\]]?\s*(=|<|>|!|in\b|not\b|is\b|between\b|like\b)/i', $sql)) {
                    $unscoped[] = "{$column} missing: {$sql}";
                }
            }
        }

        Assert::assertSame([], $unscoped, "A query on {$instance->getTable()} skipped its scope.\n".implode("\n", $unscoped));
    }

    /**
     * @param  Closure(): mixed  $request
     * @return list<string>
     */
    private function queriesDuring(Closure $request): array
    {
        /** @var list<string> $queries */
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            $queries[] = $event->sql;
        });

        $request();

        return $queries;
    }

    /**
     * The columns a model's global scopes filter on, read off a builder with the scopes applied.
     * The soft delete scope is left out: a Resource lifts it on purpose to show trashed rows.
     *
     * @return list<string>
     */
    private function scopeColumns(Model $model): array
    {
        $builder = $model->newQueryWithoutScopes();

        foreach ($model->getGlobalScopes() as $id => $scope) {
            if (! $scope instanceof SoftDeletingScope && ($scope instanceof Scope || $scope instanceof Closure)) {
                $builder->withGlobalScope((string) $id, $scope);
            }
        }

        return $this->whereColumns($builder->applyScopes()->getQuery());
    }

    /**
     * @return list<string>
     */
    private function whereColumns(Builder $query): array
    {
        $columns = [];

        foreach ($query->wheres as $where) {
            if (! is_array($where)) {
                continue;
            }

            if (isset($where['column']) && is_string($where['column'])) {
                $columns[] = $where['column'];
            }

            if (isset($where['query']) && $where['query'] instanceof Builder) {
                $columns = [...$columns, ...$this->whereColumns($where['query'])];
            }
        }

        return array_values(array_unique($columns));
    }
}
