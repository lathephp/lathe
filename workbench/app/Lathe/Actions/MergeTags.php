<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Lathe\Resources\Tag as TagResource;
use Workbench\App\Models\Tag;

/**
 * Folds the ticked tags into the one chosen in the modal: whatever carried a source tag
 * carries the target, and the source tags go.
 */
class MergeTags extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [Field::belongsTo('Target', 'target', TagResource::class)->required()];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        $key = $fields->get('target');
        $target = Tag::whereKey(is_int($key) || is_string($key) ? $key : null)->firstOrFail();

        foreach ($models as $source) {
            if (! $source instanceof Tag || $source->is($target)) {
                continue;
            }

            // A record already carrying the target keeps its own row; the rest gain the target.
            $target->products()->syncWithoutDetaching($source->products()->pluck('products.id')->all());
            $target->posts()->syncWithoutDetaching($source->posts()->pluck('posts.id')->all());
            $source->products()->detach();
            $source->posts()->detach();
            $source->delete();
        }

        return ActionResult::message('Tags merged.');
    }
}
