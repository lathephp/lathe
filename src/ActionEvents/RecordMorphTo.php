<?php

namespace Lathe\ActionEvents;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The record or target of an action event. A row outlives the model it names, so a type
 * whose class is gone loads as no record instead of failing the whole page.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphTo<TRelatedModel, TDeclaringModel>
 */
class RecordMorphTo extends MorphTo
{
    public function getEager(): Collection
    {
        foreach (array_keys($this->dictionary) as $type) {
            if (class_exists(Model::getActualClassNameForMorph((string) $type))) {
                /** @var Collection<int, TRelatedModel> $results */
                $results = $this->getResultsByType($type);
                $this->matchToMorphParents($type, $results);
            }
        }

        return $this->models;
    }
}
