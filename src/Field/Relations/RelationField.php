<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Contracts\PrimesForPage;
use Lathe\Field\Field;
use Lathe\Filters\Filter;
use Lathe\Filters\RelationFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Support\ResourceRelationCountPreloader;

/**
 * A field that shows and edits an Eloquent relation: a picker in forms, a value on index
 * and detail, or a panel on detail. The attribute is the relation's method name.
 */
abstract class RelationField extends Field implements PrimesForPage
{
    /** The sizes the inline create modal accepts, the same as an action modal's. */
    public const array MODAL_SIZES = ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl'];

    /** @var class-string<resource>|null */
    protected ?string $resourceClass = null;

    /** @var class-string<resource>|null The Resource whose fields() hold this field. */
    protected ?string $owner = null;

    protected ?Closure $titleCallback = null;

    protected ?Closure $relatableQueryCallback = null;

    protected bool|Closure $peekable = true;

    protected ?string $inverse = null;

    protected bool $reorderAssociatables = true;

    protected ?string $customSingularLabel = null;

    protected ?string $customPluralLabel = null;

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  the relation name, guessed from the label when null
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute ?? ResourceRelationshipGuesser::guessRelation($label), $resolver);

        $this->options = [
            ...$this->options,
            'withSubtitles' => false,
            'creatable' => false,
            'modalSize' => null,
            'searchable' => false,
            'debounce' => 500,
            'withTrashed' => false,
            'collapsible' => false,
            'collapsed' => false,
            'perPageOptions' => null,
            'withCount' => false,
        ];
    }

    /**
     * The kind of relation: belongsTo, hasMany, morphTo and so on.
     */
    abstract public function kind(): string;

    /**
     * Whether a related record is created from this field's panel, with a via.
     */
    public function createsThroughPanel(): bool
    {
        return false;
    }

    /**
     * Whether records are attached to and detached from this relation, with a pivot.
     */
    public function attaches(): bool
    {
        return false;
    }

    /**
     * Whether index and detail rows eager-load this relation: true for the single-parent
     * kinds whose value shows on the row.
     */
    public function loadsWithRecord(): bool
    {
        return false;
    }

    /**
     * The eager load of this relation: a soft-deleted related record still shows, every other
     * global scope stays on.
     *
     * @return array<string, Closure(Relation<*, *, *>): mixed>
     */
    public function eagerLoad(): array
    {
        return [$this->attribute => function (Relation $relation): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($relation->getRelated()), true)) {
                $relation->withoutGlobalScope(SoftDeletingScope::class);
            }
        }];
    }

    /**
     * The related record of a single-parent relation, loaded with eagerLoad() when the
     * query did not load it.
     */
    protected function relatedRecord(Model $record): ?Model
    {
        if (! $record->relationLoaded($this->attribute)) {
            $record->load($this->eagerLoad());
        }

        $related = $record->getRelation($this->attribute);

        return $related instanceof Model ? $related : null;
    }

    /**
     * The title a related record shows: display(), else the related Resource's title.
     */
    public function displayFor(Model $related): string
    {
        if ($this->titleCallback !== null) {
            $title = $this->evaluate($this->titleCallback, ['record' => $related, 'model' => $related], [$related]);

            return is_scalar($title) ? (string) $title : '';
        }

        $resource = $this->relatedResourceFor($related);
        $key = $related->getKey();

        return $resource !== null ? $resource::make($related)->title() : (is_scalar($key) ? (string) $key : '');
    }

    /**
     * One relation picker option: the key and the display, with the subtitle when the field
     * asks for it and the related Resource has one, and the avatar when it resolves one.
     *
     * @return array{value: mixed, display: string, subtitle?: string, avatar?: array<string, mixed>}
     */
    public function pickerItem(Model $related): array
    {
        $item = ['value' => $related->getKey(), 'display' => $this->displayFor($related)];
        $class = $this->relatedResourceFor($related);

        if ($class === null) {
            return $item;
        }

        $resource = $class::make($related);

        if ($this->options['withSubtitles'] === true && $resource->definition()->getSubtitle() !== '') {
            $item['subtitle'] = $resource->subtitle();
        }

        $avatar = $resource->resolveAvatar();

        if ($avatar !== null) {
            $item['avatar'] = $avatar->jsonSerialize();
        }

        return $item;
    }

    /**
     * @return class-string<resource>|null
     */
    protected function relatedResourceFor(Model $related): ?string
    {
        return $this->relatedResource();
    }

    /**
     * Panels hold no value on the record; with withCount() the index shows the related count.
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        if ($this->showsCount() && $record instanceof Model && array_key_exists($this->attribute.'_count', $record->getAttributes())) {
            return $record->getAttribute($this->attribute.'_count');
        }

        return null;
    }

    /**
     * Load the related count of every record of an index page in one query.
     *
     * @param  Collection<array-key, Model>  $records
     */
    public function primeForPage(Collection $records): void
    {
        if ($this->showsCount()) {
            ResourceRelationCountPreloader::preload($records, [$this->attribute]);
        }
    }

    /**
     * Only the kinds that write a column fill the model; the rest save through their own routes.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        return null;
    }

    /**
     * filterable() on a relation picks one related record, or one morph type.
     */
    public function makeFieldFilter(): Filter
    {
        return new RelationFilter($this, $this->filterCallback);
    }

    /**
     * The rule of a posted id list: at most the owner's idListMax ids, each one the picker
     * offers, and attach{Relation} allowed for all of them in one policy call.
     *
     * @param  class-string<resource>  $owner
     */
    protected function relatedIdsRule(LatheRequest $request, string $owner, ?Model $model): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request, $owner, $model): void {
            $ids = TagField::ids($value);
            $max = $owner::make()->definition()->getIdListMax();

            if (count($ids) > $max) {
                $fail(Lathe::__('The :attribute may not have more than :max items.', ['max' => (string) $max]));

                return;
            }

            $query = (new Associatable($request, $owner, $this))->query();

            if ($ids !== [] && ($query === null || ! $query->holdsAll($ids))) {
                $fail(Lathe::__('The :attribute must be a valid relation.'));

                return;
            }

            $ability = 'attach'.Str::studly($this->attribute);

            if ($ids !== [] && ! Container::getInstance()->make(ResourceAuthorization::class)->allows($request->user(), $owner, $ability, $model ?? $owner::newModel(), [$ids])) {
                $fail(Lathe::__('You are not authorized to relate this resource.'));
            }
        };
    }

    // The related Resource

    /**
     * @param  class-string<resource>  $class
     */
    public function resource(string $class): static
    {
        $this->mutate();
        $this->resourceClass = $class;

        return $this;
    }

    /**
     * Link the field to the Resource whose fields() hold it, so the related Resource can be
     * read from that Resource's model. Resource::fieldsFor() calls it before freezing.
     *
     * @internal
     *
     * @param  class-string<resource>  $owner
     */
    public function ownedBy(string $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return class-string<resource>|null
     */
    public function owner(): ?string
    {
        return $this->owner;
    }

    /**
     * The related Resource: resource(), else the canonical Resource of the relation's related
     * model, else App\Lathe\Resources\{StudlySingular} when that class exists.
     *
     * @return class-string<resource>|null
     */
    public function relatedResource(): ?string
    {
        return $this->resourceClass ?? ResourceRelationshipGuesser::guessResource($this->label, $this->attribute, $this->owner);
    }

    /**
     * The conventional class name the guess falls back to.
     */
    public function relatedResourceGuess(): string
    {
        return ResourceRelationshipGuesser::conventionalResource($this->label);
    }

    // Display

    /**
     * @param  Closure(Model): string  $callback
     */
    public function display(Closure $callback): static
    {
        $this->mutate();
        $this->titleCallback = $callback;

        return $this;
    }

    public function getDisplayCallback(): ?Closure
    {
        return $this->titleCallback;
    }

    public function withSubtitles(bool $withSubtitles = true): static
    {
        return $this->option('withSubtitles', $withSubtitles);
    }

    public function peekable(bool|Closure $peekable = true): static
    {
        $this->mutate();
        $this->peekable = $peekable;

        return $this;
    }

    public function noPeeking(): static
    {
        return $this->peekable(false);
    }

    public function isPeekable(LatheRequest $request): bool
    {
        return $this->decide($this->peekable, $request, null);
    }

    // Forms

    public function creatable(bool $creatable = true): static
    {
        return $this->option('creatable', $creatable);
    }

    public function modalSize(string $size): static
    {
        if (! in_array($size, self::MODAL_SIZES, true)) {
            throw new InvalidArgumentException("The modal size [{$size}] is not one of ".implode(', ', self::MODAL_SIZES).'.');
        }

        return $this->option('modalSize', $size);
    }

    public function searchable(bool $searchable = true): static
    {
        return $this->option('searchable', $searchable);
    }

    public function debounce(int $milliseconds): static
    {
        return $this->option('debounce', $milliseconds);
    }

    public function withTrashed(bool $withTrashed = true): static
    {
        return $this->option('withTrashed', $withTrashed);
    }

    public function allowsTrashed(): bool
    {
        return $this->options['withTrashed'] === true;
    }

    public function withoutTrashed(): static
    {
        return $this->withTrashed(false);
    }

    /**
     * Name the parent's relation that points back at this field, for matching a via.
     */
    public function inverse(string $relation): static
    {
        $this->mutate();
        $this->inverse = $relation;

        return $this;
    }

    public function getInverse(): ?string
    {
        return $this->inverse;
    }

    /**
     * Whether this field writes the column the parent's relation reads, for matching a via
     * without inverse(). Only the kinds that write a column can.
     *
     * @param  Relation<*, *, *>  $relation
     */
    public function writesRelation(Model $model, Relation $relation): bool
    {
        return false;
    }

    /**
     * Whether this field links a child to the parent through the parent's $relationship:
     * inverse() names it, or the field writes the relation's column.
     *
     * @param  Relation<*, *, *>  $relation
     */
    public function pointsBackThrough(Model $model, Relation $relation, string $relationship): bool
    {
        return $this->inverse === $relationship || $this->writesRelation($model, $relation);
    }

    /**
     * Whether this belongsTo or morphTo points back at the parent of a panel listing
     * $relationship, so the panel can leave it out: inverse() names the relation, or the
     * related Resource's model is the parent's class.
     */
    public function pointsBackAt(Model $parent, string $relationship): bool
    {
        if (! in_array($this->kind(), ['belongsTo', 'morphTo'], true)) {
            return false;
        }

        if ($this->inverse !== null) {
            return $this->inverse === $relationship;
        }

        $related = $this->relatedResource();

        return $related !== null && $related::make()->definition()->getModel() === $parent::class;
    }

    public function reorderAssociatables(bool $reorder = true): static
    {
        $this->mutate();
        $this->reorderAssociatables = $reorder;

        return $this;
    }

    public function reordersAssociatables(): bool
    {
        return $this->reorderAssociatables;
    }

    /**
     * @param  Closure(LatheRequest, Builder<Model>): Builder<Model>  $callback
     */
    public function relatableQuery(Closure $callback): static
    {
        $this->mutate();
        $this->relatableQueryCallback = $callback;

        return $this;
    }

    public function getRelatableQuery(): ?Closure
    {
        return $this->relatableQueryCallback;
    }

    // Labels

    public function singularLabel(string $label): static
    {
        $this->mutate();
        $this->customSingularLabel = $label;

        return $this;
    }

    public function pluralLabel(string $label): static
    {
        $this->mutate();
        $this->customPluralLabel = $label;

        return $this;
    }

    public function getSingularLabel(): string
    {
        $related = $this->relatedResource();

        return $this->customSingularLabel ?? ($related !== null ? $related::singularLabel() : $this->label);
    }

    public function getPluralLabel(): string
    {
        $related = $this->relatedResource();

        return $this->customPluralLabel ?? ($related !== null ? $related::label() : $this->label);
    }

    // Panels

    public function collapsible(bool $collapsible = true): static
    {
        return $this->option('collapsible', $collapsible);
    }

    public function collapsed(bool $collapsed = true): static
    {
        $this->option('collapsed', $collapsed);

        return $collapsed ? $this->collapsible() : $this;
    }

    /**
     * The panel's per-page choices; the first is the default and each is 1 to 100.
     *
     * @param  list<int>  $options
     */
    public function perPageOptions(array $options): static
    {
        foreach ($options as $option) {
            if ($option < 1 || $option > 100) {
                throw new InvalidArgumentException("The per-page option [{$option}] of the field [{$this->label}] must be between 1 and 100.");
            }
        }

        return $this->option('perPageOptions', $options);
    }

    /**
     * @return list<int>|null
     */
    public function getPerPageOptions(): ?array
    {
        /** @var list<int>|null */
        return $this->options['perPageOptions'];
    }

    /**
     * Show the related count: a column on index, the panel header's count on detail.
     */
    public function withCount(bool $withCount = true): static
    {
        $this->option('withCount', $withCount);

        return $withCount ? $this->showOnIndex() : $this;
    }

    public function showsCount(): bool
    {
        return $this->options['withCount'] === true;
    }

    public function jsonSerialize(): array
    {
        $related = $this->relatedResource();

        return [
            ...parent::jsonSerialize(),
            'kind' => $this->kind(),
            'relationship' => $this->attribute,
            'resource' => $related !== null && class_exists($related) ? $related::uriKey() : null,
            'singularLabel' => $this->getSingularLabel(),
            'pluralLabel' => $this->getPluralLabel(),
        ];
    }
}
