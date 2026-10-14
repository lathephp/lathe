<?php

namespace Lathe\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Relations\Associatable;
use Lathe\Field\Relations\RelationField;
use Lathe\Field\Relations\TagField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * A posted id list that a form syncs: at most the owner's idListMax ids, each added id one the
 * picker offers, attach{Relation} allowed for the added ids and detach{Relation} for the
 * removed ones. It runs for an empty list too, which removes every id.
 */
class RelatedIds implements ValidationRule
{
    /** An empty list still runs the rule: it detaches. */
    public bool $implicit = true;

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $owner
     */
    public function __construct(
        protected LatheRequest $request,
        protected string $owner,
        protected RelationField $field,
        protected ?Model $model,
    ) {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->request->exists($attribute)) {
            return;
        }

        $ids = TagField::ids($value);
        $max = $this->owner::make()->definition()->getIdListMax();

        if (count($ids) > $max) {
            $fail(Lathe::__('The :attribute may not have more than :max items.', ['max' => (string) $max]));

            return;
        }

        $current = $this->current();
        $added = array_values(array_filter($ids, fn (int|string $id) => ! in_array((string) $id, $current, true)));
        $removed = array_values(array_filter($current, fn (string $id) => ! in_array($id, array_map('strval', $ids), true)));
        $query = (new Associatable($this->request, $this->owner, $this->field))->query();

        if ($added !== [] && ($query === null || ! $query->holdsAll($added))) {
            $fail(Lathe::__('The :attribute must be a valid relation.'));

            return;
        }

        $authorization = app(ResourceAuthorization::class);
        $parent = $this->model ?? $this->owner::newModel();
        $studly = Str::studly($this->field->attribute());

        if (($added !== [] && ! $authorization->allows($this->request->user(), $this->owner, 'attach'.$studly, $parent, [$added]))
            || ($removed !== [] && ! $authorization->allows($this->request->user(), $this->owner, 'detach'.$studly, $parent, [$removed]))) {
            $fail(Lathe::__('You are not authorized to relate this resource.'));
        }
    }

    /**
     * The keys attached now, as strings; none on a create.
     *
     * @return list<string>
     */
    protected function current(): array
    {
        if ($this->model === null || ! $this->model->exists) {
            return [];
        }

        $relation = $this->model->{$this->field->attribute()}();

        if (! $relation instanceof BelongsToMany) {
            return [];
        }

        $keys = $relation->newPivotQuery()->pluck($relation->getRelatedPivotKeyName())->all();

        return array_values(array_map(fn (mixed $key) => is_scalar($key) ? (string) $key : '', $keys));
    }
}
