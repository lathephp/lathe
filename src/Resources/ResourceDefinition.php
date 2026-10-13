<?php

namespace Lathe\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Lathe\Enums\ClickAction;
use Lathe\Enums\PaginationType;
use Lathe\Enums\TableStyle;
use Lathe\Query\CountStrategy;
use Lathe\Search\SearchableColumn;
use Lathe\Support\ConfigStore;
use LogicException;

/**
 * How a Resource is set up. Built once per class by configure(), then frozen.
 */
class ResourceDefinition
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var string|Closure(Model): string */
    protected string|Closure $title = 'id';

    /** @var string|Closure(Model): string */
    protected string|Closure $subtitle = '';

    /** @var list<string> */
    protected array $with = [];

    /** @var list<int>|null */
    protected ?array $perPage = null;

    protected ?string $group = null;

    protected ?string $icon = null;

    protected ?string $navigationLabel = null;

    protected bool $canonical = true;

    protected bool $displayInNavigation = true;

    protected bool $globallySearchable = false;

    /** @var list<string|SearchableColumn> */
    protected array $searchable = [];

    protected ?bool $softDeletes = null;

    protected bool $allowAll = false;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    protected int $maxPrimaryKeySize = PHP_INT_MAX;

    protected bool $savedTabs = false;

    protected bool $trafficCop = true;

    protected int $scoutSearchResults = 200;

    protected ?bool $useScout = null;

    protected ?int $polling = null;

    protected bool $showPollingToggle = false;

    protected float $debounce = 0.5;

    protected ?PaginationType $paginationType = null;

    protected ClickAction $clickAction = ClickAction::Detail;

    protected TableStyle $tableStyle = TableStyle::Default;

    protected bool $showColumnBorders = false;

    protected bool $borderless = false;

    protected ?string $defaultGroup = null;

    protected CountStrategy $countStrategy = CountStrategy::Capped;

    /**
     * Overrides keyed by the config key they replace. An unset key is read from config
     * when asked, because a definition lives for the whole worker.
     *
     * @var array<string, int>
     */
    protected array $limits = [];

    protected bool $frozen = false;

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        return $this->set(fn () => $this->model = $model);
    }

    /**
     * @param  string|Closure(Model): string  $title
     */
    public function title(string|Closure $title): static
    {
        return $this->set(fn () => $this->title = $title);
    }

    /**
     * @param  string|Closure(Model): string  $subtitle
     */
    public function subtitle(string|Closure $subtitle): static
    {
        return $this->set(fn () => $this->subtitle = $subtitle);
    }

    /**
     * Relations always eager-loaded.
     *
     * @param  list<string>  $relations
     */
    public function with(array $relations): static
    {
        return $this->set(fn () => $this->with = $relations);
    }

    /**
     * Page size options; the first is the default.
     *
     * @param  list<int>  $options
     */
    public function perPage(array $options): static
    {
        return $this->set(fn () => $this->perPage = $options);
    }

    public function group(?string $group): static
    {
        return $this->set(fn () => $this->group = $group);
    }

    public function icon(?string $icon): static
    {
        return $this->set(fn () => $this->icon = $icon);
    }

    public function navigationLabel(?string $label): static
    {
        return $this->set(fn () => $this->navigationLabel = $label);
    }

    /**
     * Whether forModel() returns this Resource for its model.
     */
    public function canonical(bool $canonical = true): static
    {
        return $this->set(fn () => $this->canonical = $canonical);
    }

    public function displayInNavigation(bool $display = true): static
    {
        return $this->set(fn () => $this->displayInNavigation = $display);
    }

    public function globallySearchable(bool $searchable = true): static
    {
        return $this->set(fn () => $this->globallySearchable = $searchable);
    }

    /**
     * The columns `q` searches. A bare string matches by prefix.
     *
     * @param  list<string|SearchableColumn>  $columns
     */
    public function searchable(array $columns): static
    {
        return $this->set(fn () => $this->searchable = $columns);
    }

    /**
     * Null detects the SoftDeletes trait on the model.
     */
    public function softDeletes(?bool $softDeletes = true): static
    {
        return $this->set(fn () => $this->softDeletes = $softDeletes);
    }

    /**
     * Skip every policy check for this Resource. Logs a warning at boot.
     */
    public function allowAll(bool $allow = true): static
    {
        return $this->set(fn () => $this->allowAll = $allow);
    }

    /**
     * Hide the Resource from requests the callback refuses; every endpoint then answers 403.
     *
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        return $this->set(fn () => $this->seeCallback = $callback);
    }

    /**
     * Largest integer key the key search accepts.
     */
    public function maxPrimaryKeySize(int $size): static
    {
        return $this->set(fn () => $this->maxPrimaryKeySize = $size);
    }

    public function savedTabs(bool $enabled = true): static
    {
        return $this->set(fn () => $this->savedTabs = $enabled);
    }

    /**
     * Refuse an edit of a record that changed since the form loaded.
     */
    public function trafficCop(bool $enabled = true): static
    {
        return $this->set(fn () => $this->trafficCop = $enabled);
    }

    public function scoutSearchResults(int $results): static
    {
        return $this->set(fn () => $this->scoutSearchResults = $results);
    }

    /**
     * Null detects the Scout Searchable trait on the model.
     */
    public function useScout(?bool $use = true): static
    {
        return $this->set(fn () => $this->useScout = $use);
    }

    /**
     * Seconds between index refreshes.
     */
    public function polling(int $seconds): static
    {
        return $this->set(fn () => $this->polling = $seconds);
    }

    public function showPollingToggle(bool $show = true): static
    {
        return $this->set(fn () => $this->showPollingToggle = $show);
    }

    /**
     * Seconds the search box waits before it queries.
     */
    public function debounce(float $seconds): static
    {
        return $this->set(fn () => $this->debounce = $seconds);
    }

    public function paginationType(PaginationType $type): static
    {
        return $this->set(fn () => $this->paginationType = $type);
    }

    public function clickAction(ClickAction $action): static
    {
        return $this->set(fn () => $this->clickAction = $action);
    }

    public function tableStyle(TableStyle $style): static
    {
        return $this->set(fn () => $this->tableStyle = $style);
    }

    public function showColumnBorders(bool $show = true): static
    {
        return $this->set(fn () => $this->showColumnBorders = $show);
    }

    /**
     * No dividers between fields in forms and details.
     */
    public function borderless(bool $borderless = true): static
    {
        return $this->set(fn () => $this->borderless = $borderless);
    }

    /**
     * Row-grouping id applied when the URL has none.
     */
    public function defaultGroup(string $group): static
    {
        return $this->set(fn () => $this->defaultGroup = $group);
    }

    public function globalSearchResults(int $results): static
    {
        return $this->limit('global_search.results', $results);
    }

    /**
     * Rows above which a bulk run becomes a queued batch.
     */
    public function bulkInlineMax(int $max): static
    {
        return $this->limit('limits.bulk_inline_max', $max);
    }

    /**
     * How the index counts rows. A null cap keeps limits.count_cap.
     */
    public function countStrategy(CountStrategy $strategy, ?int $cap = null): static
    {
        $this->set(fn () => $this->countStrategy = $strategy);

        return $cap === null ? $this : $this->limit('limits.count_cap', $cap);
    }

    public function pickerResults(int $results): static
    {
        return $this->limit('limits.picker_results', $results);
    }

    public function searchTermChars(int $chars): static
    {
        return $this->limit('limits.search_term_chars', $chars);
    }

    public function searchTermWords(int $words): static
    {
        return $this->limit('limits.search_term_words', $words);
    }

    /**
     * Filter options above this count become a remote picker.
     */
    public function filterRemoteThreshold(int $threshold): static
    {
        return $this->limit('limits.filter_remote_threshold', $threshold);
    }

    public function idListMax(int $max): static
    {
        return $this->limit('limits.id_list_max', $max);
    }

    public function hasSeeCallback(): bool
    {
        return $this->seeCallback !== null;
    }

    public function seenBy(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    public function freeze(): static
    {
        $this->frozen = true;

        return $this;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @return string|Closure(Model): string
     */
    public function getTitle(): string|Closure
    {
        return $this->title;
    }

    /**
     * @return string|Closure(Model): string
     */
    public function getSubtitle(): string|Closure
    {
        return $this->subtitle;
    }

    /**
     * @return list<string>
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * @return list<int>|null
     */
    public function getPerPage(): ?array
    {
        return $this->perPage;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function isCanonical(): bool
    {
        return $this->canonical;
    }

    public function shouldDisplayInNavigation(): bool
    {
        return $this->displayInNavigation;
    }

    public function isGloballySearchable(): bool
    {
        return $this->globallySearchable;
    }

    /**
     * @return list<string|SearchableColumn>
     */
    public function getSearchable(): array
    {
        return $this->searchable;
    }

    public function usesSoftDeletes(): bool
    {
        if ($this->softDeletes !== null || $this->model === null) {
            return $this->softDeletes === true;
        }

        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    public function allowsAll(): bool
    {
        return $this->allowAll;
    }

    public function getMaxPrimaryKeySize(): int
    {
        return $this->maxPrimaryKeySize;
    }

    public function usesSavedTabs(): bool
    {
        return $this->savedTabs;
    }

    public function usesTrafficCop(): bool
    {
        return $this->trafficCop;
    }

    public function getScoutSearchResults(): int
    {
        return $this->scoutSearchResults;
    }

    public function getUseScout(): ?bool
    {
        return $this->useScout;
    }

    public function getPolling(): ?int
    {
        return $this->polling;
    }

    public function shouldShowPollingToggle(): bool
    {
        return $this->showPollingToggle;
    }

    public function getDebounce(): float
    {
        return $this->debounce;
    }

    /**
     * Without a count there is no last page to link to, so a Resource that counts nothing pages simply.
     */
    public function getPaginationType(): PaginationType
    {
        if ($this->countStrategy === CountStrategy::None) {
            return PaginationType::Simple;
        }

        return $this->paginationType
            ?? PaginationType::tryFrom($this->config()->string('pagination.type'))
            ?? PaginationType::Links;
    }

    public function getClickAction(): ClickAction
    {
        return $this->clickAction;
    }

    public function getTableStyle(): TableStyle
    {
        return $this->tableStyle;
    }

    public function shouldShowColumnBorders(): bool
    {
        return $this->showColumnBorders;
    }

    public function isBorderless(): bool
    {
        return $this->borderless;
    }

    public function getDefaultGroup(): ?string
    {
        return $this->defaultGroup;
    }

    public function getGlobalSearchResults(): int
    {
        return $this->getLimit('global_search.results');
    }

    public function getBulkInlineMax(): int
    {
        return $this->getLimit('limits.bulk_inline_max');
    }

    public function getCountStrategy(): CountStrategy
    {
        return $this->countStrategy;
    }

    public function getCountCap(): int
    {
        return $this->getLimit('limits.count_cap');
    }

    public function getPickerResults(): int
    {
        return $this->getLimit('limits.picker_results');
    }

    public function getSearchTermChars(): int
    {
        return $this->getLimit('limits.search_term_chars');
    }

    public function getSearchTermWords(): int
    {
        return $this->getLimit('limits.search_term_words');
    }

    public function getFilterRemoteThreshold(): int
    {
        return $this->getLimit('limits.filter_remote_threshold');
    }

    public function getIdListMax(): int
    {
        return $this->getLimit('limits.id_list_max');
    }

    /**
     * Every option with config fallbacks resolved, as the index page reads them.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'perPage' => $this->perPage ?: array_values(array_filter((array) $this->config()->get('pagination.per_page_options', []), 'is_int')),
            'maxPrimaryKeySize' => $this->maxPrimaryKeySize,
            'canonical' => $this->canonical,
            'softDeletes' => $this->usesSoftDeletes(),
            'savedTabs' => $this->savedTabs,
            'trafficCop' => $this->trafficCop,
            'globallySearchable' => $this->globallySearchable,
            'scoutSearchResults' => $this->scoutSearchResults,
            'useScout' => $this->useScout,
            'polling' => $this->polling,
            'showPollingToggle' => $this->showPollingToggle,
            'debounce' => $this->debounce,
            'paginationType' => $this->getPaginationType()->value,
            'clickAction' => $this->clickAction->value,
            'tableStyle' => $this->tableStyle->value,
            'showColumnBorders' => $this->showColumnBorders,
            'borderless' => $this->borderless,
            'globalSearchResults' => $this->getGlobalSearchResults(),
            'bulkInlineMax' => $this->getBulkInlineMax(),
            'countStrategy' => $this->countStrategy->value,
            'countCap' => $this->getCountCap(),
            'pickerResults' => $this->getPickerResults(),
            'searchTermChars' => $this->getSearchTermChars(),
            'searchTermWords' => $this->getSearchTermWords(),
            'filterRemoteThreshold' => $this->getFilterRemoteThreshold(),
            'idListMax' => $this->getIdListMax(),
            'defaultGroup' => $this->defaultGroup,
        ];
    }

    protected function limit(string $key, int $value): static
    {
        return $this->set(fn () => $this->limits[$key] = $value);
    }

    protected function getLimit(string $key): int
    {
        return $this->limits[$key] ?? $this->config()->integer($key);
    }

    protected function config(): ConfigStore
    {
        return app(ConfigStore::class);
    }

    /**
     * @param  Closure(): mixed  $change
     */
    protected function set(Closure $change): static
    {
        if ($this->frozen) {
            throw new LogicException('A Resource definition cannot change after it is built.');
        }

        $change();

        return $this;
    }
}
