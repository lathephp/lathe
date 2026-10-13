<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Filters\FilterApplicator;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\SavedTabs\SavedTab;
use Lathe\SavedTabs\SchemaHash;
use Lathe\Support\ConfigStore;

class SavedTabController
{
    use AuthorizesResources;

    public function index(LatheRequest $request, ResourceAuthorization $authorization, ConfigStore $config): JsonResponse
    {
        $class = $this->enabledResource($request, $authorization, $config);

        return new JsonResponse(['tabs' => $this->owned($request, $class)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (SavedTab $tab) => $tab->toPayload())
            ->all()]);
    }

    public function store(LatheRequest $request, ResourceAuthorization $authorization, ConfigStore $config): JsonResponse
    {
        $class = $this->enabledResource($request, $authorization, $config);
        $max = $config->integer('saved_tabs.max_per_resource');

        if ($this->owned($request, $class)->count() >= $max) {
            throw ValidationException::withMessages(['name' => "Maximum of {$max} saved tabs per resource"]);
        }

        $attributes = $this->attributes($request, $class::make(), true);
        $position = $this->owned($request, $class)->max('position');

        $tab = SavedTab::create($attributes + [
            'user_id' => $this->userId($request),
            'resource_class' => $class,
            'position' => (is_numeric($position) ? (int) $position : 0) + 1,
        ]);

        return new JsonResponse($tab->toPayload(), 201);
    }

    public function update(LatheRequest $request, ResourceAuthorization $authorization, ConfigStore $config): JsonResponse
    {
        $class = $this->enabledResource($request, $authorization, $config);
        $tab = $this->find($request, $class);
        $tab->update($this->attributes($request, $class::make(), false));

        return new JsonResponse($tab->toPayload());
    }

    /**
     * The drop sends the user's full id list in its new order. Anything but exactly their own tabs answers 422 and writes nothing.
     */
    public function reorder(LatheRequest $request, ResourceAuthorization $authorization, ConfigStore $config): Response
    {
        $class = $this->enabledResource($request, $authorization, $config);
        $ids = $request->input('ids');

        if (! is_array($ids) || ! array_is_list($ids) || $ids === [] || array_filter($ids, fn ($id) => ! is_int($id) && ! (is_string($id) && ctype_digit($id))) !== []) {
            throw ValidationException::withMessages(['ids' => 'Send the saved tab ids as a list']);
        }

        $ids = array_map(fn (mixed $id): int => is_scalar($id) ? (int) $id : 0, $ids);
        $owned = $this->owned($request, $class)->get()->map(fn (SavedTab $tab): int => $tab->id)->all();

        if (count($ids) !== count($owned) || array_diff($ids, $owned) !== [] || array_diff($owned, $ids) !== []) {
            throw ValidationException::withMessages(['ids' => 'Send every one of your saved tabs once']);
        }

        SavedTab::reorder($this->userId($request), $class, $ids);

        return new Response(null, 204);
    }

    public function destroy(LatheRequest $request, ResourceAuthorization $authorization, ConfigStore $config): Response
    {
        $class = $this->enabledResource($request, $authorization, $config);
        $this->find($request, $class)->delete();

        return new Response(null, 204);
    }

    /**
     * @return class-string<resource>
     */
    protected function enabledResource(LatheRequest $request, ResourceAuthorization $authorization, ConfigStore $config): string
    {
        $class = $this->viewableResource($request, $authorization);

        abort_unless($config->boolean('saved_tabs.enabled') && $class::make()->definition()->usesSavedTabs(), 403);

        return $class;
    }

    /**
     * The checked columns. On update only the fields sent change, and the schema hash is always renewed.
     *
     * @return array<string, mixed>
     */
    protected function attributes(LatheRequest $request, Resource $resource, bool $creating): array
    {
        /** @var array<string, mixed> $data */
        $data = Validator::make($request->all(), [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'q' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Stored as given: the applicator whitelists it against the sortable keys when it applies the tab.
            'sort' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trashed' => ['sometimes', 'nullable', Rule::in(['with', 'only'])],
            'show_count' => ['sometimes', 'boolean'],
            'badge_type' => ['sometimes', 'nullable', Rule::in(['success', 'danger', 'warning'])],
        ])->validate();

        if (array_key_exists('filters', $data)) {
            $data['filters'] = (new FilterApplicator($resource))->values($request, is_array($data['filters'] ?? null) ? $data['filters'] : []);
        }

        return $data + ['schema_hash' => SchemaHash::for($resource, $request)];
    }

    /**
     * @param  class-string<resource>  $class
     * @return Builder<SavedTab>
     */
    protected function owned(LatheRequest $request, string $class): Builder
    {
        return SavedTab::where('user_id', $this->userId($request))->where('resource_class', $class);
    }

    /**
     * @param  class-string<resource>  $class
     */
    protected function find(LatheRequest $request, string $class): SavedTab
    {
        return $this->owned($request, $class)->whereKey($request->route('savedTab'))->firstOrFail();
    }

    protected function userId(LatheRequest $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }
}
