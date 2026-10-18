<?php

namespace Lathe\Resources;

use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lathe\ActionEvents\Actionable;
use Lathe\ActionEvents\ActionEventResource;
use Lathe\Actions\Action;
use Lathe\Actions\ActionCollection;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Exceptions\ActionUriKeyCollisionException;
use Lathe\Exceptions\InvalidActionException;
use Lathe\Field\Avatar;
use Lathe\Field\Contracts\Cover;
use Lathe\Field\Contracts\Deletable;
use Lathe\Field\Contracts\Storable;
use Lathe\Field\Evaluator;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\RelationField;
use Lathe\Filters\Filter;
use Lathe\Groups\Group;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Layout\SectionPresenter;
use Lathe\Lenses\Lens;
use Lathe\Menu\MenuItem;
use Lathe\Resources\Concerns\HasMeta;
use Lathe\Search\SearchableColumn;
use Lathe\Support\SafeHtml;
use Lathe\Tabs\Tab;
use Lathe\Widgets\Widget;

/**
 * One block of the layout tree.
 *
 * @phpstan-type LayoutPanel array{type: 'panel', attribute: string, name: string, fields: list<string>, toolbar: bool, collapsible: bool, collapsed: bool, hideHeading: bool, headingInCard: bool, help: SafeHtml|null, limit: int|null, columns: int|null, columnSpan: int|null, borderless: bool}
 * @phpstan-type LayoutGrid array{type: 'grid', columns: int, items: list<array<string, mixed>>}
 * @phpstan-type LayoutTab array{attribute: string, label: string, icon: string|null, badge: string|null, badgeType: 'info'|'success'|'warning'|'danger'|null, columns: int|null, borderless: bool, items: list<array<string, mixed>>}
 * @phpstan-type LayoutTabGroup array{type: 'tab-group', attribute: string, label: string|null, queryKey: string, activeTab: int, vertical: bool, contained: bool, columnSpan: int|null, tabs: list<LayoutTab>}
 * @phpstan-type LayoutBlock LayoutPanel|LayoutGrid|LayoutTabGroup
 *
 * @phpstan-consistent-constructor
 */
abstract class Resource
{
    use HasMeta;

    /**
     * The policy class, when it is not the one the Gate resolves for the model.
     *
     * @var class-string|null
     */
    public static ?string $policy = null;

    public function __construct(protected ?Model $model = null)
    {
        //
    }

    /**
     * @return list<mixed>
     */
    abstract public function fields(LatheRequest $request): array;

    /**
     * The fields of the index when it differs from fields(); [] keeps fields().
     *
     * @return list<mixed>
     */
    public function fieldsForIndex(LatheRequest $request): array
    {
        return [];
    }

    /**
     * @return list<mixed>
     */
    public function fieldsForDetail(LatheRequest $request): array
    {
        return [];
    }

    /**
     * @return list<mixed>
     */
    public function fieldsForCreate(LatheRequest $request): array
    {
        return [];
    }

    /**
     * @return list<mixed>
     */
    public function fieldsForUpdate(LatheRequest $request): array
    {
        return [];
    }

    /**
     * @return list<mixed>
     */
    public function fieldsForPreview(LatheRequest $request): array
    {
        return [];
    }

    /**
     * @return list<mixed>
     */
    public function fieldsForPeeking(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The frozen fields of a context: its own list when it returns any, else fields().
     * Layout containers are flattened in declaration order, each field linked to its section.
     * Built once per request and context, so canSee and closures never run twice.
     */
    public function fieldsFor(FieldContext $context, LatheRequest $request): FieldCollection
    {
        $key = 'lathe.fields.'.static::class.'.'.$context->value;
        $cached = $request->attributes->get($key);

        if ($cached instanceof FieldCollection) {
            return $cached;
        }

        $fields = new FieldCollection(SectionPresenter::flattenFields($this->layoutItemsFor($context, $request), 'Details'));
        $fields->each(fn (Field $field) => ($field instanceof RelationField ? $field->ownedBy(static::class) : $field)->freeze());

        $request->attributes->set($key, $fields);

        return $fields;
    }

    /**
     * The layout tree of a context, built from the same items as fieldsFor(). Only the fields
     * shown in the context and allowed for this user appear.
     *
     * @return list<LayoutBlock>
     */
    public function layoutFor(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $fields = $this->fieldsFor($context, $request)->forContext($context, $request, $model)->authorized($request);

        /** @var list<LayoutBlock> $layout */
        $layout = SectionPresenter::layout($this->layoutItemsFor($context, $request), $fields, $request, $context, $model);

        return $layout;
    }

    /**
     * The raw items of a context, read from fields() once per request.
     *
     * @return list<mixed>
     */
    private function layoutItemsFor(FieldContext $context, LatheRequest $request): array
    {
        $key = 'lathe.layout-items.'.static::class.'.'.$context->value;
        $cached = $request->attributes->get($key);

        if (is_array($cached)) {
            return array_values($cached);
        }

        $items = match ($context) {
            FieldContext::Index => $this->fieldsForIndex($request),
            FieldContext::Detail => $this->fieldsForDetail($request),
            FieldContext::Create => $this->fieldsForCreate($request),
            FieldContext::Update => $this->fieldsForUpdate($request),
            FieldContext::Preview => $this->fieldsForPreview($request),
            FieldContext::Peeking => $this->fieldsForPeeking($request),
        };
        $items = $items === [] ? $this->fields($request) : $items;
        $items = $context === FieldContext::Detail ? $this->withActionEventsPanel($items) : $items;

        $request->attributes->set($key, $items);

        return $items;
    }

    /**
     * A model that uses Actionable gets a folded Action Events panel at the end of its detail
     * page, unless the Resource declares a field for the actions relation itself. The panel
     * shows only to a viewer who may view the log.
     *
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function withActionEventsPanel(array $items): array
    {
        $model = $this->definition()->getModel();

        if ($model === null || ! in_array(Actionable::class, class_uses_recursive($model), true)) {
            return $items;
        }

        foreach (SectionPresenter::flattenFields($items, 'Details') as $field) {
            if ($field->attribute() === 'actions') {
                return $items;
            }
        }

        return [...$items, Field::morphMany(Lathe::__('Action Events'), 'actions', ActionEventResource::class)
            ->collapsed()
            ->canSee(fn (LatheRequest $request) => app(ResourceAuthorization::class)->allows($request->user(), ActionEventResource::class, 'viewAny'))];
    }

    /**
     * @return list<Filter>
     */
    public function filters(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The cards above the index; onlyOnDetail() ones show on the detail page instead.
     *
     * @return list<Widget>
     */
    public function widgets(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The alternate indexes the toolbar lens switcher offers.
     *
     * @return list<Lens>
     */
    public function lenses(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The actions staff run from the index, the detail page and the row menu.
     *
     * @return list<Action>
     */
    public function actions(LatheRequest $request): array
    {
        return [];
    }

    /**
     * Every declared action, hidden ones included so a run of one answers 403, not 404.
     * Two actions sharing a uriKey, or a queued action with a file field, are refused.
     */
    public function availableActions(LatheRequest $request): ActionCollection
    {
        $actions = new ActionCollection($this->actions($request));
        $seen = [];

        foreach ($actions as $action) {
            $uriKey = $action->uriKey();

            if (isset($seen[$uriKey])) {
                throw new ActionUriKeyCollisionException(
                    static::class." declares actions {$seen[$uriKey]} and ".$action::class." with the uriKey \"{$uriKey}\". Give one of them a distinct withUriKey().",
                );
            }

            $seen[$uriKey] = $action::class;

            // A queued job cannot carry an uploaded file, so the class is refused here.
            if ($action instanceof ShouldQueue && $action->hasFileField($request)) {
                throw new InvalidActionException(
                    $action::class.' implements ShouldQueue and has a file field. Remove ShouldQueue or the file field.',
                );
            }
        }

        return $actions;
    }

    /**
     * The index tabs, the first being the default view.
     *
     * @return list<Tab>
     */
    public function tabs(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The row groupings the index offers, picked with ?group=.
     *
     * @return list<Group>
     */
    public function groups(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The filters this request may see and apply.
     *
     * @return list<Filter>
     */
    public function availableFilters(LatheRequest $request): array
    {
        $generated = array_map(fn (Field $field) => $field->makeFieldFilter(), $this->filterableFields($request));

        return array_values(array_filter([...$this->filters($request), ...$generated], fn (Filter $filter) => $filter->authorizedToSee($request)));
    }

    /**
     * The fields marked filterable(); each one generates a FieldFilter.
     *
     * @return list<Field>
     */
    public function filterableFields(LatheRequest $request): array
    {
        return array_values($this->fieldsFor(FieldContext::Index, $request)->filter(fn (Field $field) => $field->isFilterable())->all());
    }

    /**
     * The sidebar entry that replaces this Resource's default one; null keeps the default.
     * Static, so the sidebar calls it without building the Resource.
     */
    public static function menu(LatheRequest $request): ?MenuItem
    {
        return null;
    }

    /**
     * Values for a custom page or resource plugin, sent as `extra` in the Resource's payload.
     *
     * @return array<string, mixed>
     */
    public static function additionalInformation(LatheRequest $request): array
    {
        return [];
    }

    public static function make(?Model $model = null): static
    {
        return new static($model);
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition;
    }

    /**
     * The frozen definition, built once per class by the registry.
     */
    public function definition(): ResourceDefinition
    {
        return app(ResourceRegistry::class)->definition(static::class);
    }

    /**
     * Build a fresh definition. Only the registry calls this.
     *
     * @internal
     */
    public function buildDefinition(): ResourceDefinition
    {
        // Attributes first, so a fluent call in configure() wins.
        return $this->configure((new ResourceDefinition)->applyClassAttributes(static::class))->freeze();
    }

    /**
     * @return class-string<Model>
     */
    public static function model(): string
    {
        $model = static::make()->definition()->getModel();

        return $model ?? throw new \LogicException(static::class.' has no model.');
    }

    public static function newModel(): Model
    {
        $model = static::model();

        return new $model;
    }

    /**
     * The base model query. Only ResourceQuery calls this.
     *
     * @internal
     *
     * @return Builder<Model>
     */
    public static function query(): Builder
    {
        return static::newModel()->newQuery();
    }

    /**
     * Narrow the index rows, counts and exports.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow the rows a detail, delete or restore can reach.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow the rows an edit form, update or inline edit can reach.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow global search results; defaults to indexQuery().
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function searchQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * Narrow the rows a widget reads; defaults to indexQuery().
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function widgetQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * Narrow the records a relation picker offers.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow the records a replicate can copy.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function replicateQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow a Scout search.
     */
    public static function scoutQuery(LatheRequest $request, mixed $query): mixed
    {
        return $query;
    }

    /**
     * Runs inside the delete's transaction, before the delete.
     */
    /**
     * Abilities this Resource never grants, whatever its policy says, such as every write of a
     * read-only log.
     *
     * @return list<string>
     */
    public static function deniedAbilities(): array
    {
        return [];
    }

    public static function beforeDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs after the delete's transaction commits.
     */
    public static function afterDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs inside the force delete's transaction, before the force delete.
     */
    public static function beforeForceDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs after the force delete's transaction commits.
     */
    public static function afterForceDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs inside the restore's transaction, before the restore.
     */
    public static function beforeRestore(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs after the restore's transaction commits.
     */
    public static function afterRestore(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Attributes a new model starts with, under the field defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaultAttributes(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The attributes a replica leaves empty. The columns of every file field are left empty
     * as well, so a replica never points at the source's file.
     *
     * @return list<string>
     */
    public static function replicateExcept(): array
    {
        return [];
    }

    /**
     * An unsaved copy of a stored model, without replicateExcept() and the file columns.
     *
     * @throws InvalidArgumentException when the model is not stored
     */
    public static function replicate(Model $model): Model
    {
        if (! $model->exists) {
            throw new InvalidArgumentException('Only a stored model can be replicated.');
        }

        $except = static::replicateExcept();

        foreach (static::make()->fieldsFor(FieldContext::Create, app(LatheRequest::class)) as $field) {
            if ($field instanceof Storable && $field instanceof Deletable) {
                $except = [...$except, ...$field->columnsThatShouldBeDeleted()];
            }
        }

        return $model->replicate($except);
    }

    /**
     * Runs after the rules of a create or an update; add errors to the validator to refuse the write.
     */
    public static function afterValidation(LatheRequest $request, Validator $validator): void
    {
        //
    }

    public static function afterCreationValidation(LatheRequest $request, Validator $validator): void
    {
        //
    }

    public static function afterUpdateValidation(LatheRequest $request, Validator $validator): void
    {
        //
    }

    /**
     * Runs inside the create transaction, before the fields fill the model.
     */
    public static function beforeCreate(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs once the create has committed.
     */
    public static function afterCreate(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs inside the update transaction, before the fields fill the model.
     */
    public static function beforeUpdate(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs once the update has committed.
     */
    public static function afterUpdate(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Whether writes to this Resource leave action event rows.
     */
    public static function recordsActionEvents(): bool
    {
        return true;
    }

    /**
     * Where the panel goes after a create: the new record's detail page.
     */
    public static function redirectAfterCreate(LatheRequest $request, self $resource): string
    {
        return static::uriKey().'/'.static::keyOf($resource);
    }

    /**
     * Where the panel goes after an update: the record's detail page.
     */
    public static function redirectAfterUpdate(LatheRequest $request, self $resource): string
    {
        return static::uriKey().'/'.static::keyOf($resource);
    }

    /**
     * The detail page's breadcrumbs; null keeps Dashboard / Resource / title. A child declared
     * with parent() leads through its parent: Dashboard / Orders / ORD-12 / Items / title.
     *
     * @return list<array{label: string, href: string|null}>|null
     */
    public function detailBreadcrumbs(LatheRequest $request): ?array
    {
        [$parent, $relationship] = $this->definition()->getParent() ?? [null, ''];
        $relation = $parent !== null ? $parent::newModel()->{$relationship}() : null;

        if ($parent === null || ! $relation instanceof HasOneOrMany || $this->model === null) {
            return null;
        }

        // The parent's key is read from the child's foreign key.
        $parentKey = $this->model->getAttribute($relation->getForeignKeyName());

        if (! is_scalar($parentKey)) {
            return null;
        }

        $field = $parent::make()->fieldsFor(FieldContext::Detail, $request)->first(fn (Field $field) => $field->attribute() === $relationship);
        $payload = app(RecordPayload::class);
        // The parent shows by its title only to a user who may view it, else by its key.
        $parentModel = $parent::newModel()->newQuery()->find($parentKey);
        $titled = $parentModel !== null && app(ResourceAuthorization::class)->allows($request->user(), $parent, 'view', $parentModel);

        return $payload->breadcrumbs($parent, [
            ['label' => $titled ? $parent::make($parentModel)->breadcrumbLabel() : '#'.$parentKey, 'href' => $payload->href('resources/'.$parent::uriKey().'/'.$parentKey)],
            ['label' => $field instanceof Field ? $field->label() : static::label(), 'href' => null],
            ['label' => $this->breadcrumbLabel(), 'href' => null],
        ]);
    }

    /**
     * The label of the create form's main button.
     */
    public static function createButtonLabel(): string
    {
        return Lathe::__('Create :resource', ['resource' => static::singularLabel()]);
    }

    /**
     * The label of the edit form's main button.
     */
    public static function updateButtonLabel(): string
    {
        return Lathe::__('Update :resource', ['resource' => static::singularLabel()]);
    }

    protected static function keyOf(self $resource): string
    {
        $key = $resource->getModel()?->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Where the panel goes after a delete, force delete or restore.
     */
    public static function redirectAfterDelete(LatheRequest $request): string
    {
        return static::uriKey();
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function forModel(?Model $model): static
    {
        return new static($model);
    }

    /**
     * The column that tells one index row from another on a page.
     */
    public static function rowKey(): string
    {
        return static::newModel()->getKeyName();
    }

    public static function uriKey(): string
    {
        return Str::plural(Str::kebab(class_basename(static::class)));
    }

    public static function label(): string
    {
        return Str::plural(Str::headline(class_basename(static::class)));
    }

    public static function singularLabel(): string
    {
        return Str::singular(Str::headline(class_basename(static::class)));
    }

    public function title(): string
    {
        return $this->resolveText($this->definition()->getTitle());
    }

    /**
     * What a breadcrumb calls this record: its title, or #id when the title is empty.
     *
     * @internal
     */
    public function breadcrumbLabel(): string
    {
        $title = $this->title();

        return $title !== '' ? $title : '#'.static::keyOf($this);
    }

    public function subtitle(): string
    {
        return $this->resolveText($this->definition()->getSubtitle());
    }

    public function globalSearchSubtitle(): string
    {
        return $this->resolveText($this->definition()->getGlobalSearchSubtitle());
    }

    /**
     * The picture beside the title: the first Cover field of the detail fields. A record
     * with no picture of its own gets the initials of its title.
     */
    public function resolveAvatar(): ?Avatar
    {
        if ($this->model === null) {
            return null;
        }

        // The request's own LatheRequest, so the fields build once however many records ask.
        $request = Evaluator::currentRequest() ?? LatheRequest::createFrom(request());
        $cover = $this->fieldsFor(FieldContext::Detail, $request)
            ->forContext(FieldContext::Detail, $request, $this->model)
            ->authorized($request)
            ->first(fn (Field $field) => $field instanceof Cover);

        if (! $cover instanceof Cover) {
            return null;
        }

        $avatar = $cover->resolveAvatar($this->model);

        return $avatar->url === null && $avatar->initials === null
            ? new Avatar(null, Avatar::initialsOf($this->title()), $avatar->style, $avatar->rounded)
            : $avatar;
    }

    /**
     * The columns a relation picker reads: the key, the title and subtitle when they are
     * attributes, and the Cover field's attribute. A display() Closure reading another
     * column adds it here.
     *
     * @return list<string>
     */
    public function optionColumns(): array
    {
        $request = Evaluator::currentRequest() ?? LatheRequest::createFrom(request());
        $cover = $this->fieldsFor(FieldContext::Detail, $request)->first(fn (Field $field) => $field instanceof Cover);
        $definition = $this->definition();

        return array_values(array_unique(array_filter([
            static::newModel()->getKeyName(),
            is_string($definition->getTitle()) ? $definition->getTitle() : null,
            is_string($definition->getSubtitle()) ? $definition->getSubtitle() : null,
            $cover !== null && ! $cover->isComputed() && ! str_contains($cover->attribute(), '.') ? $cover->attribute() : null,
        ], fn (?string $column) => $column !== null && $column !== '')));
    }

    /**
     * @return list<string>
     */
    public function with(): array
    {
        return $this->definition()->getWith();
    }

    /**
     * The columns the definition declares searchable.
     *
     * @return list<string|SearchableColumn>
     */
    public function searchableColumns(): array
    {
        return $this->definition()->getSearchable();
    }

    public function usesScout(): bool
    {
        return $this->definition()->usesScout();
    }

    /**
     * @param  string|Closure(Model): string  $source
     */
    protected function resolveText(string|Closure $source): string
    {
        if ($this->model === null || $source === '') {
            return '';
        }

        if ($source instanceof Closure) {
            return $source($this->model);
        }

        $value = $this->model->getAttribute($source);

        return is_scalar($value) ? (string) $value : '';
    }
}
