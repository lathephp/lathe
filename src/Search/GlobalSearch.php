<?php

namespace Lathe\Search;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;
use Throwable;

/**
 * Searches every globally searchable Resource the user may see, in sidebar order,
 * then the registered providers, and groups the rows for the palette.
 *
 * @phpstan-import-type ManifestEntry from ResourceRegistry
 *
 * @phpstan-type Row array{resourceTitle: string, title: string, subTitle: string|null, resourceName: string, resourceId: mixed, avatar: string|null, rounded: bool, url: string|null}
 * @phpstan-type Group array{resourceName: string, label: string, results: list<Row>, count?: array{value: int, exact: bool, capped: bool, estimated: bool}|null}
 */
#[Singleton]
class GlobalSearch
{
    /** Rows one request returns at most, across every group. */
    public const int MAX_ROWS = 100;

    /** @var list<SearchProvider> */
    protected array $providers = [];

    public function __construct(protected ResourceRegistry $resources, protected ConfigStore $config)
    {
        //
    }

    public function register(SearchProvider ...$providers): void
    {
        $this->providers = [...$this->providers, ...array_values($providers)];
    }

    /**
     * The groups for the request's `q`, and the labels of the Resources left out because
     * their query failed or the row cap was reached. A term over the limits is a 422.
     *
     * @return array{groups: list<Group>, skipped: list<string>}
     */
    public function search(LatheRequest $request, ResourceAuthorization $authorization): array
    {
        $term = SearchApplicator::term(
            $request->query('q'),
            $this->config->integer('limits.search_term_chars'),
            $this->config->integer('limits.search_term_words'),
        );

        if ($term === null) {
            return ['groups' => [], 'skipped' => []];
        }

        $groups = [];
        $skipped = [];
        $remaining = self::MAX_ROWS;

        foreach ($this->candidates() as $entry) {
            if (! $authorization->allowsViewAnyFromManifest($request, $entry)) {
                continue;
            }

            if ($remaining <= 0) {
                $skipped[] = $entry['label'];

                continue;
            }

            try {
                [$rows, $count] = $this->resourceRows($request, $entry, $remaining);
            } catch (Throwable $e) {
                report($e);
                $skipped[] = $entry['label'];

                continue;
            }

            if ($rows !== []) {
                $groups[] = ['resourceName' => $entry['uriKey'], 'label' => $entry['label'], 'results' => $rows, 'count' => $count];
                $remaining -= count($rows);
            }
        }

        foreach ($this->providers as $provider) {
            if ($remaining <= 0) {
                continue;
            }

            try {
                $results = $provider->authorize($request) ? $provider->search($request) : [];
            } catch (Throwable $e) {
                report($e);
                $skipped[] = $provider->title();

                continue;
            }

            // The palette opens a row by its url, so a row without one is left out.
            $rows = array_filter(array_map(fn (GlobalSearchResult $result) => $result->toRow($provider), $results), fn (array $row) => $row['url'] !== null);
            $rows = array_slice($rows, 0, min($this->config->integer('global_search.results'), $remaining));

            if ($rows !== []) {
                $groups[] = ['resourceName' => $provider->uriKey(), 'label' => $provider->title(), 'results' => $rows];
                $remaining -= count($rows);
            }
        }

        return ['groups' => $groups, 'skipped' => $skipped];
    }

    /**
     * One Resource's rows through its scoped query in the Search context, and the count of
     * every match, up to 1,000, when there are more than the rows.
     *
     * @param  ManifestEntry  $entry
     * @return array{list<Row>, array{value: int, exact: bool, capped: bool, estimated: bool}|null}
     */
    protected function resourceRows(LatheRequest $request, array $entry, int $remaining): array
    {
        $resource = $entry['class']::make();

        // A Resource with no search column would match every row; Scout counts as one.
        if ($resource->searchableColumns() === [] && ! $resource->usesScout()) {
            return [[], null];
        }

        $definition = $resource->definition();
        $limit = min($definition->getGlobalSearchResults(), $remaining);
        $query = new ResourceQuery($resource, $request, QueryContext::Search);
        // One row past the limit tells whether the group has more without counting.
        $models = $query->searchResults($limit + 1);
        $count = $models->count() > $limit ? $query->searchCount()?->toArray() : null;
        $models = $models->take($limit);

        // A title or subtitle closure may read the relations the Resource eager loads.
        if ($definition->getTitle() instanceof Closure || $definition->getGlobalSearchSubtitle() instanceof Closure) {
            $models->load($resource->with());
        }

        $rows = [];

        foreach ($models as $model) {
            $record = $resource->forModel($model);
            $key = $model->getKey();
            // The palette draws the title's initials when the cover has no image.
            $avatar = $record->resolveAvatar();

            $rows[] = [
                'resourceTitle' => $entry['label'],
                'title' => $record->title(),
                'subTitle' => $record->globalSearchSubtitle() !== '' ? $record->globalSearchSubtitle() : null,
                'resourceName' => $entry['uriKey'],
                'resourceId' => $key,
                'avatar' => $avatar?->url,
                'rounded' => $avatar !== null && $avatar->rounded,
                'url' => '/resources/'.$entry['uriKey'].'/'.(is_scalar($key) ? $key : ''),
            ];
        }

        return [$rows, $count];
    }

    /**
     * The globally searchable manifest entries in the sidebar's order: groups as they first
     * appear, Resources by label inside each group.
     *
     * @return list<ManifestEntry>
     */
    protected function candidates(): array
    {
        $groups = [];

        foreach ($this->resources->manifest() as $entry) {
            if ($entry['globallySearchable']) {
                $groups[$entry['group'] ?? 'Resources'][$entry['label']] = $entry;
            }
        }

        $candidates = [];

        foreach ($groups as $entries) {
            ksort($entries);
            array_push($candidates, ...array_values($entries));
        }

        return $candidates;
    }
}
