<?php

namespace Lathe\Actions;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Lathe\Http\Requests\LatheRequest;

/**
 * The actions of one Resource, with the filters and the order the Actions menu uses.
 *
 * @extends Collection<int, Action>
 */
class ActionCollection extends Collection
{
    /**
     * The actions shown on one display: index, detail or table-row.
     */
    public function forDisplay(string $display): static
    {
        return $this->filter(fn (Action $action) => match ($display) {
            'index' => $action->shownOnIndex(),
            'detail' => $action->shownOnDetail(),
            'table-row' => $action->shownOnTableRow(),
            default => throw new InvalidArgumentException("Unknown action display \"{$display}\"; use index, detail or table-row."),
        })->values();
    }

    public function authorizedToSee(LatheRequest $request): static
    {
        return $this->filter(fn (Action $action) => $action->authorizedToSee($request))->values();
    }

    /**
     * Standalone actions first, then ungrouped ones, then each group in the order its first
     * action appears, and destructive actions last. Declaration order holds within each part.
     */
    public function menuOrder(): static
    {
        $groups = [];

        foreach ($this->items as $action) {
            $group = $action->getGroup();

            if ($group !== null) {
                $groups[$group] ??= count($groups);
            }
        }

        return $this->sortBy(function (Action $action) use ($groups) {
            $group = $action->getGroup();

            return match (true) {
                $action->isStandalone() => [0, 0],
                $action->isDestructive() => [3, 0],
                $group === null => [1, 0],
                default => [2, $groups[$group]],
            };
        })->values();
    }

    /**
     * @return array{sole: int, standalone: int, resource: int}
     */
    public function counts(): array
    {
        $sole = $this->filter(fn (Action $action) => $action->isSole())->count();
        $standalone = $this->filter(fn (Action $action) => ! $action->isSole() && $action->isStandalone())->count();

        return ['sole' => $sole, 'standalone' => $standalone, 'resource' => $this->count() - $sole - $standalone];
    }

    public function findByUriKey(string $uriKey): ?Action
    {
        return $this->first(fn (Action $action) => $action->uriKey() === $uriKey);
    }
}
