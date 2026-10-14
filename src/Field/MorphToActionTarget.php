<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Contracts\PrimesForPage;
use Lathe\Lathe;

/**
 * What an action event was done with: the target Resource's singular label and the record's
 * title, a link only when the viewer may open it. A model no Resource covers shows its class
 * name and `#id`. Read only; the abilities of a page come from one call per Resource.
 */
class MorphToActionTarget extends Field implements PrimesForPage
{
    protected string $component = 'belongs-to-field';

    /** @var array<string, bool> Whether the viewer may view each target of the page, by "class:key". */
    protected array $viewable = [];

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->exceptOnForms();
    }

    /**
     * Load the targets of the page by type and check view on all of them in one batch.
     *
     * @param  Collection<array-key, Model>  $records
     */
    public function primeForPage(Collection $records): void
    {
        $events = $records->filter(fn (Model $record) => $record instanceof ActionEvent && $record->target_type !== null);

        // A type whose class is gone cannot be eager loaded; its row shows the type's name.
        $loadable = new Collection;

        foreach ($events as $event) {
            if ($event->relationLoaded('target')) {
                continue;
            }

            if ($this->modelClass($event) === null) {
                $event->setRelation('target', null);
            } else {
                $loadable->push($event);
            }
        }

        $loadable->load('target');

        $targets = $events->map(fn (Model $event) => $event->getRelation('target'))->filter(fn (mixed $target) => $target instanceof Model);
        $authorization = Container::getInstance()->make(ResourceAuthorization::class);
        $user = Evaluator::currentRequest()?->user();

        foreach ($targets->groupBy(fn (Model $target) => $target::class) as $class => $group) {
            $resource = Lathe::resources()->forModel((string) $class);

            if ($resource === null) {
                continue;
            }

            $abilities = $authorization->abilitiesFor($user, $resource, new Collection($group->values()->all()), ['view']);

            foreach ($group as $target) {
                $this->viewable[$class.':'.$this->keyOf($target)] = ($abilities[$this->keyOf($target)]['view'] ?? false) === true;
            }
        }
    }

    /**
     * @return array{value: string, display: string, href: string|null}|null
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        if (! $record instanceof ActionEvent || $record->target_type === null) {
            return null;
        }

        if (! $record->relationLoaded('target')) {
            $this->primeForPage((new Collection)->push($record));
        }

        $id = (string) $record->target_id;
        $class = $this->modelClass($record);
        $resource = $class === null ? null : Lathe::resources()->forModel($class);
        $target = $record->getRelation('target');

        if ($class === null || $resource === null) {
            return ['value' => $id, 'display' => class_basename($class ?? $record->target_type).' #'.$id, 'href' => null];
        }

        if (! $target instanceof Model) {
            return ['value' => $id, 'display' => $resource::singularLabel().' #'.$id, 'href' => null];
        }

        $viewable = $this->viewable[$class.':'.$this->keyOf($target)] ?? false;

        return [
            'value' => $id,
            'display' => $resource::singularLabel().': '.$resource::make($target)->title(),
            'href' => $viewable ? $this->detailPath($resource::uriKey(), $id) : null,
        ];
    }

    /**
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolve($model);

        return ['value' => $value, 'display' => is_array($value) ? $value['display'] : ''];
    }

    /**
     * The model class of the row's target type, or null when no such class exists.
     *
     * @return class-string<Model>|null
     */
    protected function modelClass(ActionEvent $event): ?string
    {
        $type = (string) $event->target_type;
        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    protected function keyOf(Model $model): string
    {
        return ActionEvent::keyOf($model);
    }

    protected function detailPath(string $uriKey, string $id): string
    {
        $base = '/'.trim(Lathe::config()->string('path'), '/');

        return rtrim($base, '/').'/resources/'.$uriKey.'/'.$id;
    }
}
